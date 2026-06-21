<template>
  <div class="flash-sale-form page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">{{ isEdit ? '编辑秒杀活动' : '新建秒杀活动' }}</span>
            <span class="card-subtitle">绑定商品、设置秒杀价与活动库存</span>
          </div>
          <el-button @click="$router.back()" round>返回</el-button>
        </div>
      </template>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
        style="max-width: 600px;"
      >
        <el-form-item label="活动名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入活动名称" />
        </el-form-item>

        <el-form-item label="商品" prop="product_id">
          <el-select
            v-model="form.product_id"
            placeholder="请选择商品"
            filterable
            style="width: 100%"
            @change="handleProductChange"
          >
            <el-option
              v-for="product in products"
              :key="product.id"
              :label="`${product.name} (${product.sku}) - 原价: ¥${product.price} / 库存: ${product.stock_quantity}`"
              :value="product.id"
              :disabled="product.status !== 'active'"
            />
          </el-select>
        </el-form-item>

        <el-form-item v-if="selectedProduct" label="商品原价">
          <span class="original-price-display">¥{{ selectedProduct.price }}</span>
        </el-form-item>

        <el-form-item label="秒杀价" prop="flash_price">
          <el-input-number v-model="form.flash_price" :min="0.01" :precision="2" style="width: 100%" />
        </el-form-item>

        <el-form-item label="活动库存" prop="activity_stock">
          <el-input-number v-model="form.activity_stock" :min="1" style="width: 100%" />
          <div class="form-tip">活动库存与商品常规库存隔离，下单仅扣减活动库存</div>
        </el-form-item>

        <el-form-item label="每人限购" prop="per_limit">
          <el-input-number v-model="form.per_limit" :min="1" style="width: 100%" />
        </el-form-item>

        <el-form-item label="开始时间" prop="start_time">
          <el-date-picker
            v-model="form.start_time"
            type="datetime"
            placeholder="选择开始时间"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="结束时间" prop="end_time">
          <el-date-picker
            v-model="form.end_time"
            type="datetime"
            placeholder="选择结束时间"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
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
import { flashSaleApi } from '@/api/modules/flashSale'
import { productApi } from '@/api/modules/product'

const route = useRoute()
const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const isEdit = ref(false)
const products = ref([])

const form = reactive({
  name: '',
  product_id: null,
  flash_price: 0.01,
  activity_stock: 1,
  per_limit: 1,
  start_time: '',
  end_time: '',
})

const rules = {
  name: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
  product_id: [{ required: true, message: '请选择商品', trigger: 'change' }],
  flash_price: [{ required: true, message: '请输入秒杀价', trigger: 'blur' }],
  activity_stock: [{ required: true, message: '请输入活动库存', trigger: 'blur' }],
  start_time: [{ required: true, message: '请选择开始时间', trigger: 'change' }],
  end_time: [{ required: true, message: '请选择结束时间', trigger: 'change' }],
}

const selectedProduct = computed(() => {
  if (!form.product_id) return null
  return products.value.find((p) => p.id === form.product_id)
})

const handleProductChange = () => {
  if (selectedProduct.value) {
    form.flash_price = selectedProduct.value.price
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
        if (isEdit.value) {
          await flashSaleApi.updateFlashSale(route.params.id, form)
          ElMessage.success('更新成功')
        } else {
          await flashSaleApi.createFlashSale(form)
          ElMessage.success('创建成功')
        }
        router.push('/flash-sales')
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
      const res = await flashSaleApi.getFlashSale(route.params.id)
      Object.assign(form, {
        name: res.data.name,
        product_id: res.data.product_id,
        flash_price: res.data.flash_price,
        activity_stock: res.data.activity_stock,
        per_limit: res.data.per_limit || 1,
        start_time: res.data.start_time,
        end_time: res.data.end_time,
      })
    } catch (error) {
      ElMessage.error('获取活动信息失败')
    }
  }
})
</script>

<style scoped>
.flash-sale-form {
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

.original-price-display {
  font-size: 16px;
  color: #9ca3af;
  text-decoration: line-through;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}
</style>
