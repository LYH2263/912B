<template>
  <div class="quality-inspection-form page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">{{ isEdit ? '编辑质检记录' : '新建质检记录' }}</span>
            <span class="card-subtitle">登记入库商品质检信息，合格数量将计入可售库存</span>
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
            <el-form-item label="质检商品" prop="product_id">
              <el-select
                v-model="form.product_id"
                placeholder="请选择商品"
                filterable
                @change="handleProductChange"
                style="width: 100%"
              >
                <el-option
                  v-for="product in products"
                  :key="product.id"
                  :label="`${product.name} (${product.sku})`"
                  :value="product.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="关联采购单" prop="purchase_order_id">
              <el-select
                v-model="form.purchase_order_id"
                placeholder="请选择采购单（可选）"
                filterable
                clearable
                style="width: 100%"
              >
                <el-option
                  v-for="po in purchaseOrders"
                  :key="po.id"
                  :label="`${po.purchase_order_no} - ${po.supplier_name}`"
                  :value="po.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="质检员" prop="inspector">
              <el-input v-model="form.inspector" placeholder="请输入质检员姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="质检日期" prop="inspection_date">
              <el-date-picker
                v-model="form.inspection_date"
                type="date"
                placeholder="请选择质检日期"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">质检数量</el-divider>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="合格数量" prop="qualified_quantity">
              <el-input-number
                v-model="form.qualified_quantity"
                :min="0"
                :controls="true"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="不合格数量" prop="unqualified_quantity">
              <el-input-number
                v-model="form.unqualified_quantity"
                :min="0"
                :controls="true"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="总数量">
              <el-input :model-value="totalQuantity" disabled />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="合格率">
              <el-progress
                :percentage="passRate"
                :color="getPassRateColor(passRate)"
                :stroke-width="16"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item
              label="不合格原因"
              prop="unqualified_reason"
              :required="form.unqualified_quantity > 0"
            >
              <el-input
                v-model="form.unqualified_reason"
                type="textarea"
                :rows="3"
                :placeholder="form.unqualified_quantity > 0 ? '请填写不合格原因' : '无不合格商品时无需填写'"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="备注">
              <el-input
                v-model="form.remark"
                type="textarea"
                :rows="2"
                placeholder="请输入备注信息（可选）"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-alert
          v-if="form.qualified_quantity > 0"
          type="info"
          :closable="false"
          show-icon
          style="margin-bottom: 20px"
        >
          <template #title>
            合格数量 <b>{{ form.qualified_quantity }}</b> 将自动增加到商品可售库存
          </template>
        </el-alert>
        <el-alert
          v-if="form.unqualified_quantity > 0"
          type="warning"
          :closable="false"
          show-icon
          style="margin-bottom: 20px"
        >
          <template #title>
            不合格数量 <b>{{ form.unqualified_quantity }}</b> 不会进入可售库存
          </template>
        </el-alert>

        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="loading">
            {{ isEdit ? '保存修改' : '提交质检' }}
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
import { qualityInspectionApi } from '@/api/modules/qualityInspection'
import { productApi } from '@/api/modules/product'
import { purchaseOrderApi } from '@/api/modules/purchaseOrder'

const router = useRouter()
const route = useRoute()
const formRef = ref(null)
const loading = ref(false)
const products = ref([])
const purchaseOrders = ref([])
const isEdit = computed(() => !!route.params.id)

const form = reactive({
  product_id: null,
  purchase_order_id: null,
  qualified_quantity: 0,
  unqualified_quantity: 0,
  unqualified_reason: '',
  inspector: '',
  inspection_date: '',
  remark: '',
})

const rules = {
  product_id: [
    { required: true, message: '请选择质检商品', trigger: 'change' },
  ],
  inspector: [
    { required: true, message: '请输入质检员姓名', trigger: 'blur' },
  ],
  inspection_date: [
    { required: true, message: '请选择质检日期', trigger: 'change' },
  ],
  qualified_quantity: [
    {
      validator: (rule, value, callback) => {
        if (value === 0 && form.unqualified_quantity === 0) {
          callback(new Error('合格数量和不合格数量不能同时为0'))
        } else {
          callback()
        }
      },
      trigger: 'change',
    },
  ],
  unqualified_quantity: [
    {
      validator: (rule, value, callback) => {
        if (value === 0 && form.qualified_quantity === 0) {
          callback(new Error('合格数量和不合格数量不能同时为0'))
        } else if (value > 0 && !form.unqualified_reason.trim()) {
          callback(new Error('存在不合格商品时必须填写不合格原因'))
        } else {
          callback()
        }
      },
      trigger: 'change',
    },
  ],
  unqualified_reason: [
    {
      validator: (rule, value, callback) => {
        if (form.unqualified_quantity > 0 && !value?.trim()) {
          callback(new Error('请填写不合格原因'))
        } else {
          callback()
        }
      },
      trigger: 'blur',
    },
  ],
}

const totalQuantity = computed(() => {
  return (form.qualified_quantity || 0) + (form.unqualified_quantity || 0)
})

const passRate = computed(() => {
  if (totalQuantity.value === 0) return 0
  return Math.round((form.qualified_quantity / totalQuantity.value) * 10000) / 100
})

const getPassRateColor = (rate) => {
  if (rate >= 95) return '#67c23a'
  if (rate >= 80) return '#e6a23c'
  return '#f56c6c'
}

const handleProductChange = () => {
}

const fetchProducts = async () => {
  try {
    const res = await productApi.getProducts({ per_page: 1000 })
    products.value = res.data
  } catch (error) {
    ElMessage.error('获取商品列表失败')
  }
}

const fetchPurchaseOrders = async () => {
  try {
    const res = await purchaseOrderApi.getPurchaseOrders({ per_page: 1000 })
    purchaseOrders.value = res.data
  } catch (error) {
    ElMessage.error('获取采购单列表失败')
  }
}

const fetchInspection = async () => {
  try {
    const res = await qualityInspectionApi.getQualityInspection(route.params.id)
    const data = res.data
    form.product_id = data.product_id
    form.purchase_order_id = data.purchase_order_id
    form.qualified_quantity = data.qualified_quantity
    form.unqualified_quantity = data.unqualified_quantity
    form.unqualified_reason = data.unqualified_reason || ''
    form.inspector = data.inspector
    form.inspection_date = data.inspection_date
    form.remark = data.remark || ''
  } catch (error) {
    ElMessage.error('获取质检记录详情失败')
    router.push('/quality-inspections')
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    loading.value = true
    try {
      const submitData = {
        product_id: form.product_id,
        purchase_order_id: form.purchase_order_id || null,
        qualified_quantity: form.qualified_quantity,
        unqualified_quantity: form.unqualified_quantity,
        unqualified_reason: form.unqualified_reason || null,
        inspector: form.inspector,
        inspection_date: form.inspection_date,
        remark: form.remark || null,
      }

      if (isEdit.value) {
        await qualityInspectionApi.updateQualityInspection(route.params.id, submitData)
        ElMessage.success('质检记录更新成功')
      } else {
        await qualityInspectionApi.createQualityInspection(submitData)
        ElMessage.success('质检记录提交成功')
      }
      router.push('/quality-inspections')
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      loading.value = false
    }
  })
}

onMounted(() => {
  fetchProducts()
  fetchPurchaseOrders()
  if (isEdit.value) {
    fetchInspection()
  } else {
    form.inspection_date = new Date().toISOString().split('T')[0]
  }
})
</script>

<style scoped>
.quality-inspection-form {
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
</style>
