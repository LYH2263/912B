<template>
  <div class="purchase-order-form page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">{{ isEdit ? '编辑采购单' : '创建采购单' }}</span>
            <span class="card-subtitle">填写供应商信息、预计到货日期与采购商品明细</span>
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
        <el-divider content-position="left">基本信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="供应商名称" prop="supplier_name">
              <el-input v-model="form.supplier_name" placeholder="请输入供应商名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预计到货日" prop="expected_arrival_date">
              <el-date-picker
                v-model="form.expected_arrival_date"
                type="date"
                placeholder="请选择预计到货日期"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系人" prop="supplier_contact">
              <el-input v-model="form.supplier_contact" placeholder="请输入联系人（可选）" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系电话" prop="supplier_phone">
              <el-input v-model="form.supplier_phone" placeholder="请输入联系电话（可选）" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">采购商品</el-divider>
        <el-form-item label="商品明细" prop="items">
          <el-table :data="form.items" border style="width: 100%">
            <el-table-column label="商品" width="280">
              <template #default="{ row, $index }">
                <el-select
                  v-model="row.product_id"
                  placeholder="请选择商品"
                  filterable
                  @change="handleProductChange($index)"
                  style="width: 100%"
                >
                  <el-option
                    v-for="product in products"
                    :key="product.id"
                    :label="`${product.name} (${product.sku})`"
                    :value="product.id"
                  />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="当前库存" width="100" align="center">
              <template #default="{ row }">
                <span v-if="row.product_id">
                  {{ getProductStock(row.product_id) }}
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="参考成本价" width="120" align="center">
              <template #default="{ row }">
                <span v-if="row.product_id">
                  ¥{{ getProductCostPrice(row.product_id).toFixed(2) }}
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="采购单价" width="160">
              <template #default="{ row, $index }">
                <el-input-number
                  v-model="row.purchase_price"
                  :min="0"
                  :precision="2"
                  :step="1"
                  @change="calculateTotal"
                  style="width: 100%"
                />
              </template>
            </el-table-column>
            <el-table-column label="采购数量" width="160">
              <template #default="{ row }">
                <el-input-number
                  v-model="row.quantity"
                  :min="1"
                  @change="calculateTotal"
                  style="width: 100%"
                />
              </template>
            </el-table-column>
            <el-table-column label="小计" width="120" align="center">
              <template #default="{ row }">
                <span v-if="row.product_id && row.quantity">
                  ¥{{ (row.purchase_price * row.quantity).toFixed(2) }}
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center">
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

        <el-form-item label="备注">
          <el-input
            v-model="form.remark"
            type="textarea"
            :rows="3"
            placeholder="请输入备注信息（可选）"
          />
        </el-form-item>

        <el-form-item>
          <div class="amount-summary">
            <div class="amount-row">
              <span class="amount-label">采购金额：</span>
              <span class="amount-value">¥{{ totalAmount.toFixed(2) }}</span>
            </div>
          </div>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSave(false)" :loading="loading">
            保存草稿
          </el-button>
          <el-button type="success" @click="handleSave(true)" :loading="loading">
            保存并提交
          </el-button>
          <el-button @click="$router.back()">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { purchaseOrderApi } from '@/api/modules/purchaseOrder'
import { productApi } from '@/api/modules/product'

const router = useRouter()
const route = useRoute()
const formRef = ref(null)
const loading = ref(false)
const products = ref([])
const isEdit = computed(() => !!route.params.id)

const form = reactive({
  supplier_name: '',
  supplier_contact: '',
  supplier_phone: '',
  expected_arrival_date: '',
  remark: '',
  items: [
    {
      product_id: null,
      purchase_price: 0,
      quantity: 1,
    },
  ],
})

const rules = {
  supplier_name: [
    { required: true, message: '请输入供应商名称', trigger: 'blur' },
  ],
  expected_arrival_date: [
    { required: true, message: '请选择预计到货日期', trigger: 'change' },
  ],
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
          if (!value[i].purchase_price || value[i].purchase_price < 0) {
            callback(new Error('请输入有效的采购单价'))
            return
          }
          if (!value[i].quantity || value[i].quantity < 1) {
            callback(new Error('请输入有效的采购数量'))
            return
          }
        }
        callback()
      },
      trigger: 'change',
    },
  ],
}

const totalAmount = computed(() => {
  let total = 0
  form.items.forEach((item) => {
    if (item.product_id && item.quantity) {
      total += item.purchase_price * item.quantity
    }
  })
  return total
})

const getProductStock = (productId) => {
  const product = products.value.find((p) => p.id === productId)
  return product ? product.stock_quantity : 0
}

const getProductCostPrice = (productId) => {
  const product = products.value.find((p) => p.id === productId)
  return product ? product.cost_price : 0
}

const handleProductChange = (index) => {
  const item = form.items[index]
  if (item.product_id) {
    const costPrice = getProductCostPrice(item.product_id)
    if (costPrice > 0 && item.purchase_price === 0) {
      item.purchase_price = costPrice
    }
  }
}

const addItem = () => {
  form.items.push({
    product_id: null,
    purchase_price: 0,
    quantity: 1,
  })
}

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
    calculateTotal()
  }
}

const calculateTotal = () => {
}

const fetchProducts = async () => {
  try {
    const res = await productApi.getProducts({ per_page: 1000 })
    products.value = res.data
  } catch (error) {
    ElMessage.error('获取商品列表失败')
  }
}

const fetchPurchaseOrder = async () => {
  try {
    const res = await purchaseOrderApi.getPurchaseOrder(route.params.id)
    const data = res.data
    form.supplier_name = data.supplier_name
    form.supplier_contact = data.supplier_contact || ''
    form.supplier_phone = data.supplier_phone || ''
    form.expected_arrival_date = data.expected_arrival_date
    form.remark = data.remark || ''
    form.items = data.items.map((item) => ({
      product_id: item.product_id,
      purchase_price: item.purchase_price,
      quantity: item.quantity,
    }))
  } catch (error) {
    ElMessage.error('获取采购单详情失败')
    router.push('/purchase-orders')
  }
}

const handleSave = async (submit) => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    loading.value = true
    try {
      const orderData = {
        supplier_name: form.supplier_name,
        supplier_contact: form.supplier_contact || null,
        supplier_phone: form.supplier_phone || null,
        expected_arrival_date: form.expected_arrival_date,
        remark: form.remark || null,
        items: form.items.map((item) => ({
          product_id: item.product_id,
          purchase_price: item.purchase_price,
          quantity: item.quantity,
        })),
        submit: submit,
      }

      if (isEdit.value) {
        delete orderData.submit
        await purchaseOrderApi.updatePurchaseOrder(route.params.id, orderData)
        ElMessage.success('采购单更新成功')
      } else {
        await purchaseOrderApi.createPurchaseOrder(orderData)
        ElMessage.success(submit ? '采购单提交成功' : '采购单保存成功')
      }
      router.push('/purchase-orders')
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      loading.value = false
    }
  })
}

onMounted(() => {
  fetchProducts()
  if (isEdit.value) {
    fetchPurchaseOrder()
  }
})
</script>

<style scoped>
.purchase-order-form {
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

.amount-summary {
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

.amount-label {
  color: #6b7280;
  min-width: 80px;
}

.amount-value {
  font-size: 20px;
  font-weight: 700;
  color: #f56c6c;
}
</style>
