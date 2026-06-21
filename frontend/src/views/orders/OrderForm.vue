<template>
  <div class="order-form page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">创建订单</span>
            <span class="card-subtitle">选择商品、设置数量并填写收货信息</span>
          </div>
          <el-button @click="$router.back()" round>返回</el-button>
        </div>
      </template>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
      >
        <!-- 订单商品 -->
        <el-form-item label="订单商品" prop="items">
          <el-table :data="form.items" border style="width: 100%">
            <el-table-column label="商品" width="300">
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
                    :disabled="product.stock_quantity === 0 || product.status !== 'active'"
                  />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="单价" width="120">
              <template #default="{ row }">
                <span v-if="row.product_id">
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
                  @change="calculateTotal"
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
                  :disabled="form.items.length === 1"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <el-button
            type="primary"
            @click="addItem"
            style="margin-top: 10px"
          >
            添加商品
          </el-button>
        </el-form-item>

        <!-- 订单金额 -->
        <el-form-item label="订单金额">
          <div class="amount-section">
            <div class="amount-row">
              <span class="amount-label">商品总额：</span>
              <span class="amount-value">¥{{ totalAmount.toFixed(2) }}</span>
            </div>
            <div class="amount-row">
              <span class="amount-label">优惠金额：</span>
              <el-input-number
                v-model="form.discount_amount"
                :min="0"
                :max="Math.max(totalAmount, 0)"
                :precision="2"
                @change="onDiscountChange"
                style="width: 150px"
              />
            </div>
            <div class="amount-row" v-if="memberInfo">
              <span class="amount-label">积分抵扣：</span>
              <div class="points-section">
                <el-switch
                  v-model="usePoints"
                  :disabled="!memberInfo.points || maxUsablePoints === 0"
                  @change="onUsePointsChange"
                />
                <span class="points-info" v-if="memberInfo.points">
                  (可用 {{ memberInfo.points }} 积分，最多抵扣 {{ maxUsablePoints }} 积分，抵 ¥{{ (maxUsablePoints / 100).toFixed(2) }})
                </span>
                <span class="points-info text-muted" v-else>
                  (暂无可用积分)
                </span>
              </div>
            </div>
            <div class="amount-row" v-if="usePoints && form.points_used > 0">
              <span class="amount-label">使用积分：</span>
              <el-input-number
                v-model="form.points_used"
                :min="0"
                :max="maxUsablePoints"
                :step="100"
                @change="onPointsChange"
                style="width: 160px"
              />
              <span class="points-discount">
                抵扣 ¥{{ pointsDiscountAmount.toFixed(2) }}
              </span>
            </div>
            <div class="amount-row final">
              <span class="amount-label">实付金额：</span>
              <span class="amount-value final-amount">¥{{ finalAmount.toFixed(2) }}</span>
            </div>
          </div>
        </el-form-item>

        <!-- 收货信息 -->
        <el-divider content-position="left">收货信息</el-divider>
        <el-form-item label="收货人" prop="shipping_name">
          <el-input v-model="form.shipping_name" placeholder="请输入收货人姓名" />
        </el-form-item>
        <el-form-item label="联系电话" prop="shipping_phone">
          <el-input v-model="form.shipping_phone" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="收货地址" prop="shipping_address">
          <el-input
            v-model="form.shipping_address"
            type="textarea"
            :rows="3"
            placeholder="请输入收货地址"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="form.remark"
            type="textarea"
            :rows="3"
            placeholder="请输入备注信息（可选）"
          />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="loading">
            创建订单
          </el-button>
          <el-button @click="$router.back()">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { orderApi } from '@/api/modules/order'
import { productApi } from '@/api/modules/product'
import { memberApi } from '@/api/modules/member'
import { authApi } from '@/api/modules/auth'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const products = ref([])
const memberInfo = ref(null)
const usePoints = ref(false)
const currentUser = ref(null)

const form = reactive({
  items: [
    {
      product_id: null,
      quantity: 1,
    },
  ],
  discount_amount: 0,
  points_used: 0,
  user_id: null,
  shipping_name: '',
  shipping_phone: '',
  shipping_address: '',
  remark: '',
})

const rules = {
  items: [
    {
      validator: (rule, value, callback) => {
        if (!value || value.length === 0) {
          callback(new Error('请至少添加一个商品'))
          return
        }
        for (let i = 0; i < value.length; i++) {
          if (!value[i].product_id) {
            callback(new Error('请选择商品'))
            return
          }
          if (!value[i].quantity || value[i].quantity < 1) {
            callback(new Error('请输入有效的数量'))
            return
          }
        }
        callback()
      },
      trigger: 'change',
    },
  ],
  shipping_name: [
    { required: true, message: '请输入收货人姓名', trigger: 'blur' },
  ],
  shipping_phone: [
    { required: true, message: '请输入联系电话', trigger: 'blur' },
    {
      pattern: /^1[3-9]\d{9}$/,
      message: '请输入正确的手机号码',
      trigger: 'blur',
    },
  ],
  shipping_address: [
    { required: true, message: '请输入收货地址', trigger: 'blur' },
  ],
}

const availableProducts = computed(() => {
  return products.value.filter(
    (p) => p.status === 'active' && p.stock_quantity > 0
  )
})

const totalAmount = computed(() => {
  let total = 0
  form.items.forEach((item) => {
    if (item.product_id && item.quantity) {
      const price = getProductPrice(item.product_id)
      total += price * item.quantity
    }
  })
  return total
})

const maxUsablePoints = computed(() => {
  const maxByOrder = Math.floor(totalAmount.value * 0.2 * 100)
  const available = memberInfo.value?.points || 0
  return Math.min(maxByOrder, available)
})

const pointsDiscountAmount = computed(() => {
  return form.points_used / 100
})

const finalAmount = computed(() => {
  return Math.max(0, totalAmount.value - (form.discount_amount || 0) - pointsDiscountAmount.value)
})

const getProductPrice = (productId) => {
  const product = products.value.find((p) => p.id === productId)
  return product ? product.price : 0
}

const getProductStock = (productId) => {
  const product = products.value.find((p) => p.id === productId)
  return product ? product.stock_quantity : 0
}

const handleProductChange = (index) => {
  const item = form.items[index]
  if (item.product_id) {
    const stock = getProductStock(item.product_id)
    if (item.quantity > stock) {
      item.quantity = stock
      ElMessage.warning('数量不能超过库存')
    }
  }
}

const addItem = () => {
  form.items.push({
    product_id: null,
    quantity: 1,
  })
}

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

const onDiscountChange = () => {
  if (usePoints.value) {
    adjustPointsToMax()
  }
}

const onUsePointsChange = (val) => {
  if (val) {
    adjustPointsToMax()
  } else {
    form.points_used = 0
  }
}

const onPointsChange = () => {
  if (form.points_used > maxUsablePoints.value) {
    form.points_used = maxUsablePoints.value
  }
}

const adjustPointsToMax = () => {
  form.points_used = maxUsablePoints.value
}

watch(totalAmount, () => {
  if (usePoints.value && form.points_used > maxUsablePoints.value) {
    form.points_used = maxUsablePoints.value
  }
})

const fetchProducts = async () => {
  try {
    const res = await productApi.getProducts({ per_page: 1000, status: 'active' })
    products.value = res.data
  } catch (error) {
    ElMessage.error('获取商品列表失败')
  }
}

const fetchCurrentUser = async () => {
  try {
    const res = await authApi.getMe()
    currentUser.value = res.data
    form.user_id = res.data.id
  } catch (error) {
    console.warn('获取当前用户失败', error)
  }
}

const fetchMemberInfo = async () => {
  try {
    const res = await memberApi.getMemberInfo()
    memberInfo.value = res.data
  } catch (error) {
    console.warn('获取会员信息失败', error)
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    loading.value = true
    try {
      const orderData = {
        items: form.items.map((item) => ({
          product_id: item.product_id,
          quantity: item.quantity,
        })),
        discount_amount: form.discount_amount || 0,
        points_used: usePoints.value ? form.points_used : 0,
        user_id: form.user_id,
        shipping_name: form.shipping_name,
        shipping_phone: form.shipping_phone,
        shipping_address: form.shipping_address,
        remark: form.remark || '',
      }

      await orderApi.createOrder(orderData)
      ElMessage.success('订单创建成功')
      router.push('/orders')
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '订单创建失败')
    } finally {
      loading.value = false
    }
  })
}

onMounted(() => {
  fetchProducts()
  fetchCurrentUser()
  fetchMemberInfo()
})
</script>

<style scoped>
.order-form {
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

.amount-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 20px;
  background: linear-gradient(135deg, #fafbff 0%, #f0f4ff 100%);
  border-radius: 12px;
  border: 1px solid #e5e9f2;
}

.amount-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
}

.amount-row.final {
  padding-top: 10px;
  border-top: 1px dashed #d0d5e0;
}

.amount-label {
  color: #6b7280;
  min-width: 80px;
}

.amount-value {
  color: #1f2933;
  font-weight: 500;
}

.amount-value.final-amount {
  font-size: 20px;
  font-weight: 700;
  color: #f56c6c;
}

.points-section {
  display: flex;
  align-items: center;
  gap: 10px;
}

.points-info {
  font-size: 13px;
  color: #6b7280;
}

.points-info.text-muted {
  color: #c0c4cc;
}

.points-discount {
  font-size: 14px;
  color: #67c23a;
  font-weight: 500;
  margin-left: 8px;
}
</style>
