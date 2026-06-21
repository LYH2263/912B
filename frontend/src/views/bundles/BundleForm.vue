<template>
  <div class="bundle-form page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">{{ isEdit ? '编辑套餐' : '新增套餐' }}</span>
            <span class="card-subtitle">选择 2-5 个商品组合，设置优惠套餐价</span>
          </div>
        </div>
      </template>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
      >
        <el-form-item label="套餐名称" prop="name">
          <el-input v-model="form.name" placeholder="如：手机+耳机套装" />
        </el-form-item>
        <el-form-item label="SKU" prop="sku">
          <el-input v-model="form.sku" placeholder="请输入套餐SKU" />
        </el-form-item>
        <el-form-item label="套餐描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入套餐描述（可选）"
          />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="form.status" placeholder="请选择状态">
            <el-option label="上架" value="active" />
            <el-option label="下架" value="inactive" />
          </el-select>
        </el-form-item>

        <el-divider content-position="left">套餐商品（2-5 件）</el-divider>
        <el-form-item label="包含商品" prop="items">
          <el-table :data="form.items" border style="width: 100%">
            <el-table-column label="商品" width="320">
              <template #default="{ row, $index }">
                <el-select
                  v-model="row.product_id"
                  placeholder="请选择商品"
                  filterable
                  @change="handleProductChange($index)"
                  style="width: 100%"
                >
                  <el-option
                    v-for="product in availableProducts"
                    :key="product.id"
                    :label="`${product.name} (${product.sku}) - 库存: ${product.stock_quantity}`"
                    :value="product.id"
                    :disabled="isProductSelected($index, product.id) || product.stock_quantity === 0 || product.status !== 'active'"
                  />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="单价" width="120">
              <template #default="{ row }">
                <span v-if="row.product_id" class="product-price">
                  ¥{{ getProductPrice(row.product_id).toFixed(2) }}
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="数量" width="150">
              <template #default="{ row, $index }">
                <el-input-number
                  v-model="row.quantity"
                  :min="1"
                  :max="row.product_id ? Math.max(getProductStock(row.product_id), 1) : 999999"
                  @change="calculatePrices"
                />
              </template>
            </el-table-column>
            <el-table-column label="小计" width="120">
              <template #default="{ row }">
                <span v-if="row.product_id && row.quantity">
                  ¥{{ (getProductPrice(row.product_id) * row.quantity).toFixed(2) }}
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ $index }">
                <el-button
                  type="danger"
                  size="small"
                  @click="removeItem($index)"
                  :disabled="form.items.length <= 2"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="item-actions">
            <el-button
              type="primary"
              @click="addItem"
              :disabled="form.items.length >= 5"
              style="margin-top: 10px"
            >
              添加商品
            </el-button>
            <span class="item-hint" v-if="form.items.length < 2">
              至少还需添加 {{ 2 - form.items.length }} 件商品
            </span>
            <span class="item-hint ok" v-else>
              当前 {{ form.items.length }} 件商品，已满足要求
            </span>
          </div>
        </el-form-item>

        <el-divider content-position="left">价格设置</el-divider>
        <el-form-item label="原总价">
          <span class="original-total">¥{{ originalTotal.toFixed(2) }}</span>
          <span class="hint">（子商品单独购买总和）</span>
        </el-form-item>
        <el-form-item label="套餐总价" prop="total_price">
          <el-input-number
            v-model="form.total_price"
            :min="0"
            :max="Math.max(originalTotal - 0.01, 0)"
            :precision="2"
            @change="calculatePrices"
          />
        </el-form-item>
        <el-form-item label="优惠信息">
          <div class="discount-info" v-if="form.total_price > 0 && originalTotal > 0">
            <el-tag type="success" size="large">
              优惠 ¥{{ discountAmount.toFixed(2) }}
            </el-tag>
            <el-tag type="danger" size="large" effect="light">
              {{ discountPercent.toFixed(1) }}% OFF
            </el-tag>
          </div>
          <div class="discount-warning" v-else-if="form.total_price >= originalTotal && originalTotal > 0">
            <el-tag type="warning">套餐总价必须低于原总价</el-tag>
          </div>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="loading">
            保存
          </el-button>
          <el-button @click="$router.back()">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { bundleApi } from '@/api/modules/bundle'
import { productApi } from '@/api/modules/product'

const route = useRoute()
const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const isEdit = ref(false)
const products = ref([])

const form = reactive({
  name: '',
  sku: '',
  description: '',
  total_price: 0,
  status: 'active',
  items: [
    { product_id: null, quantity: 1 },
    { product_id: null, quantity: 1 },
  ],
})

const rules = {
  name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
  sku: [{ required: true, message: '请输入套餐SKU', trigger: 'blur' }],
  total_price: [{ required: true, message: '请输入套餐总价', trigger: 'blur' }],
  items: [
    {
      validator: (rule, value, callback) => {
        if (!value || value.length < 2) {
          callback(new Error('套餐至少需要包含 2 个子商品'))
          return
        }
        if (value.length > 5) {
          callback(new Error('套餐最多只能包含 5 个子商品'))
          return
        }
        const productIds = []
        for (let i = 0; i < value.length; i++) {
          if (!value[i].product_id) {
            callback(new Error('请选择所有商品'))
            return
          }
          if (productIds.includes(value[i].product_id)) {
            callback(new Error('套餐中不能包含重复的商品'))
            return
          }
          productIds.push(value[i].product_id)
          if (!value[i].quantity || value[i].quantity < 1) {
            callback(new Error('请输入有效的商品数量'))
            return
          }
        }
        if (form.total_price >= originalTotal.value) {
          callback(new Error('套餐总价必须低于子商品单独购买总和'))
          return
        }
        callback()
      },
      trigger: 'change',
    },
  ],
}

const availableProducts = computed(() => {
  return products.value
})

const originalTotal = computed(() => {
  let total = 0
  form.items.forEach((item) => {
    if (item.product_id && item.quantity) {
      total += getProductPrice(item.product_id) * item.quantity
    }
  })
  return total
})

const discountAmount = computed(() => {
  return Math.max(0, originalTotal.value - (form.total_price || 0))
})

const discountPercent = computed(() => {
  if (originalTotal.value <= 0) return 0
  return (discountAmount.value / originalTotal.value) * 100
})

const getProductPrice = (productId) => {
  const product = products.value.find((p) => p.id === productId)
  return product ? product.price : 0
}

const getProductStock = (productId) => {
  const product = products.value.find((p) => p.id === productId)
  return product ? product.stock_quantity : 0
}

const isProductSelected = (currentIndex, productId) => {
  for (let i = 0; i < form.items.length; i++) {
    if (i !== currentIndex && form.items[i].product_id === productId) {
      return true
    }
  }
  return false
}

const handleProductChange = (index) => {
  const item = form.items[index]
  if (item.product_id) {
    const stock = getProductStock(item.product_id)
    if (item.quantity > stock && stock > 0) {
      item.quantity = stock
      ElMessage.warning('数量不能超过库存')
    }
  }
  calculatePrices()
}

const addItem = () => {
  if (form.items.length < 5) {
    form.items.push({ product_id: null, quantity: 1 })
  }
}

const removeItem = (index) => {
  if (form.items.length > 2) {
    form.items.splice(index, 1)
    calculatePrices()
  }
}

const calculatePrices = () => {
  if (!isEdit.value && originalTotal.value > 0 && form.total_price === 0) {
    form.total_price = Math.max(0, originalTotal.value * 0.9)
  }
}

const fetchProducts = async () => {
  try {
    const res = await productApi.getProducts({ per_page: 1000, status: 'active' })
    products.value = res.data
  } catch (error) {
    ElMessage.error('获取商品列表失败')
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (valid) {
      loading.value = true
      try {
        const submitData = {
          name: form.name,
          sku: form.sku,
          description: form.description || null,
          total_price: form.total_price,
          status: form.status,
          items: form.items.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
          })),
        }
        if (isEdit.value) {
          await bundleApi.updateBundle(route.params.id, submitData)
          ElMessage.success('更新成功')
        } else {
          await bundleApi.createBundle(submitData)
          ElMessage.success('创建成功')
        }
        router.push('/bundles')
      } catch (error) {
        ElMessage.error(error.response?.data?.message || '操作失败')
      } finally {
        loading.value = false
      }
    }
  })
}

onMounted(async () => {
  await fetchProducts()
  if (route.params.id) {
    isEdit.value = true
    try {
      const res = await bundleApi.getBundle(route.params.id)
      const bundle = res.data
      form.name = bundle.name
      form.sku = bundle.sku
      form.description = bundle.description || ''
      form.total_price = bundle.total_price
      form.status = bundle.status
      if (bundle.bundle_items && bundle.bundle_items.length > 0) {
        form.items = bundle.bundle_items.map((item) => ({
          product_id: item.product_id,
          quantity: item.quantity,
        }))
      }
    } catch (error) {
      ElMessage.error('获取套餐信息失败')
    }
  }
})
</script>

<style scoped>
.bundle-form {
  padding: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
}

.card-subtitle {
  font-size: 12px;
  color: #6b7280;
}

.product-price {
  color: #6366f1;
  font-weight: 500;
}

.item-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.item-hint {
  font-size: 12px;
  color: #ef4444;
  margin-top: 10px;
}

.item-hint.ok {
  color: #67c23a;
}

.original-total {
  font-size: 16px;
  font-weight: 600;
  color: #374151;
}

.hint {
  font-size: 12px;
  color: #9ca3af;
  margin-left: 8px;
}

.discount-info {
  display: flex;
  gap: 10px;
}

.discount-warning {
  display: flex;
}
</style>
