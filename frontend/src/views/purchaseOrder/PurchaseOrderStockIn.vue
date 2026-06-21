<template>
  <div class="purchase-order-stock-in page-shell">
    <el-card v-if="purchaseOrder">
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">采购入库确认</span>
            <span class="card-subtitle">确认商品实际入库数量，完成后将增加库存</span>
          </div>
          <el-button @click="$router.back()" round>返回</el-button>
        </div>
      </template>

      <el-descriptions :column="2" border class="info-section">
        <el-descriptions-item label="采购单号">
          {{ purchaseOrder.purchase_order_no }}
        </el-descriptions-item>
        <el-descriptions-item label="供应商">
          {{ purchaseOrder.supplier_name }}
        </el-descriptions-item>
        <el-descriptions-item label="预计到货日">
          {{ purchaseOrder.expected_arrival_date }}
        </el-descriptions-item>
        <el-descriptions-item label="采购状态">
          <el-tag :type="getStatusType(purchaseOrder.status)">
            {{ getStatusText(purchaseOrder.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="采购金额">
          ¥{{ purchaseOrder.total_amount.toFixed(2) }}
        </el-descriptions-item>
        <el-descriptions-item label="入库进度">
          <el-progress
            :percentage="stockInProgress"
            :stroke-width="16"
            :color="progressColor"
          />
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">入库明细</el-divider>

      <el-alert
        title="入库说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>请核对实际到货数量，输入本次入库数量。</p>
        <p>入库完成后系统将自动增加对应商品库存，并记录库存变动日志（类型：入库）。</p>
      </el-alert>

      <el-table :data="stockInItems" border style="width: 100%">
        <el-table-column label="商品SKU" width="140">
          <template #default="{ row }">
            {{ row.product_sku }}
          </template>
        </el-table-column>
        <el-table-column label="商品名称" min-width="180">
          <template #default="{ row }">
            {{ row.product_name }}
          </template>
        </el-table-column>
        <el-table-column label="采购单价" width="120" align="center">
          <template #default="{ row }">
            ¥{{ row.purchase_price.toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column label="采购数量" width="100" align="center">
          <template #default="{ row }">
            {{ row.quantity }}
          </template>
        </el-table-column>
        <el-table-column label="已入库" width="100" align="center">
          <template #default="{ row }">
            <span class="text-success">{{ row.received_quantity }}</span>
          </template>
        </el-table-column>
        <el-table-column label="待入库" width="100" align="center">
          <template #default="{ row }">
            <span class="text-warning">{{ row.remaining_quantity }}</span>
          </template>
        </el-table-column>
        <el-table-column label="当前库存" width="100" align="center">
          <template #default="{ row }">
            {{ row.product?.stock_quantity || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="本次入库" width="180">
          <template #default="{ row }">
            <el-input-number
              v-model="row.stock_in_quantity"
              :min="0"
              :max="row.remaining_quantity"
              :disabled="row.remaining_quantity === 0"
              style="width: 100%"
            />
          </template>
        </el-table-column>
        <el-table-column label="入库后库存" width="120" align="center">
          <template #default="{ row }">
            <span class="text-primary">
              {{ (row.product?.stock_quantity || 0) + (row.stock_in_quantity || 0) }}
            </span>
          </template>
        </el-table-column>
      </el-table>

      <div class="action-section">
        <div class="stock-in-summary">
          <div class="summary-item">
            <span class="summary-label">本次入库总数：</span>
            <span class="summary-value">{{ totalStockInQuantity }}</span>
          </div>
        </div>
        <el-button
          type="primary"
          size="large"
          @click="handleStockIn"
          :loading="loading"
          :disabled="totalStockInQuantity === 0"
        >
          确认入库
        </el-button>
        <el-button size="large" @click="$router.back()">取消</el-button>
      </div>
    </el-card>

    <el-card v-else-loading>
      <el-skeleton :rows="10" animated />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { purchaseOrderApi } from '@/api/modules/purchaseOrder'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const purchaseOrder = ref(null)
const stockInItems = ref([])

const getStatusType = (status) => {
  const map = {
    draft: 'info',
    pending: 'warning',
    partial: '',
    completed: 'success',
  }
  return map[status] || ''
}

const getStatusText = (status) => {
  const map = {
    draft: '草稿',
    pending: '待入库',
    partial: '部分入库',
    completed: '已完成',
  }
  return map[status] || status
}

const stockInProgress = computed(() => {
  if (!purchaseOrder.value) return 0
  const total = purchaseOrder.value.total_quantity
  const received = purchaseOrder.value.total_received_quantity
  return total > 0 ? Math.round((received / total) * 100) : 0
})

const progressColor = computed(() => {
  if (stockInProgress.value === 100) return '#67c23a'
  if (stockInProgress.value > 0) return '#e6a23c'
  return '#909399'
})

const totalStockInQuantity = computed(() => {
  return stockInItems.value.reduce((sum, item) => sum + (item.stock_in_quantity || 0), 0)
})

const fetchPurchaseOrder = async () => {
  try {
    const res = await purchaseOrderApi.getPurchaseOrder(route.params.id)
    purchaseOrder.value = res.data

    if (!purchaseOrder.value.can_stock_in) {
      ElMessage.warning('该采购单当前状态不允许入库')
      router.back()
      return
    }

    stockInItems.value = purchaseOrder.value.items
      .filter((item) => !item.is_fully_received)
      .map((item) => ({
        ...item,
        stock_in_quantity: item.remaining_quantity,
      }))
  } catch (error) {
    ElMessage.error('获取采购单详情失败')
    router.back()
  }
}

const handleStockIn = async () => {
  try {
    const itemsToStockIn = stockInItems.value
      .filter((item) => item.stock_in_quantity > 0)
      .map((item) => ({
        id: item.id,
        quantity: item.stock_in_quantity,
      }))

    if (itemsToStockIn.length === 0) {
      ElMessage.warning('请输入入库数量')
      return
    }

    const totalQuantity = itemsToStockIn.reduce((sum, item) => sum + item.quantity, 0)
    await ElMessageBox.confirm(
      `确定要将 ${totalQuantity} 件商品入库吗？入库后库存将自动增加。`,
      '确认入库',
      {
        type: 'warning',
        confirmButtonText: '确认入库',
        cancelButtonText: '取消',
      }
    )

    loading.value = true
    await purchaseOrderApi.stockInPurchaseOrder(route.params.id, {
      items: itemsToStockIn,
    })
    ElMessage.success('入库成功')
    router.push('/purchase-orders')
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '入库失败')
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchPurchaseOrder()
})
</script>

<style scoped>
.purchase-order-stock-in {
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

.info-section {
  margin-bottom: 20px;
}

.text-success {
  color: #67c23a;
  font-weight: 500;
}

.text-warning {
  color: #e6a23c;
  font-weight: 500;
}

.text-primary {
  color: #409eff;
  font-weight: 600;
}

.action-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.stock-in-summary {
  display: flex;
  gap: 30px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.summary-label {
  color: #6b7280;
  font-size: 15px;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  color: #409eff;
}
</style>
