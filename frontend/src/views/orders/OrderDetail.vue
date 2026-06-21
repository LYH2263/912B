<template>
  <div class="order-detail page-shell">
    <el-card v-loading="loading">
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">订单详情</span>
            <span class="card-subtitle">查看订单金额、状态与收货信息</span>
          </div>
          <el-button @click="$router.back()" round>返回</el-button>
        </div>
      </template>

      <div v-if="order" class="order-info">
        <el-descriptions title="订单信息" :column="2" border>
          <el-descriptions-item label="订单号">{{ order.order_no }}</el-descriptions-item>
          <el-descriptions-item label="订单状态">
            <el-tag :type="getStatusType(order.status)">
              {{ getStatusText(order.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="订单金额">¥{{ order.total_amount.toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="优惠金额">¥{{ order.discount_amount.toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="实付金额">
            <span class="final-amount">¥{{ order.final_amount.toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ order.created_at }}</el-descriptions-item>
          <el-descriptions-item label="支付时间" v-if="order.paid_at">{{ order.paid_at }}</el-descriptions-item>
          <el-descriptions-item label="发货时间" v-if="order.shipped_at">{{ order.shipped_at }}</el-descriptions-item>
          <el-descriptions-item label="完成时间" v-if="order.completed_at">{{ order.completed_at }}</el-descriptions-item>
          <el-descriptions-item label="取消时间" v-if="order.cancelled_at">{{ order.cancelled_at }}</el-descriptions-item>
          <el-descriptions-item label="来源" v-if="order.split_merge_type">
            <el-tag type="info" size="small">
              {{ order.split_merge_type === 'split' ? '拆分订单' : '合并订单' }}
            </el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="收货信息" :column="2" border style="margin-top: 20px">
          <el-descriptions-item label="收货人">{{ order.shipping_name }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ order.shipping_phone }}</el-descriptions-item>
          <el-descriptions-item label="收货地址" :span="2">{{ order.shipping_address }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ order.remark || '无' }}</el-descriptions-item>
        </el-descriptions>

        <div style="margin-top: 20px">
          <h3>订单商品</h3>
          <el-table :data="order.order_items" border>
            <el-table-column prop="product_name" label="商品名称" />
            <el-table-column prop="product_sku" label="SKU" width="150" />
            <el-table-column prop="product_price" label="单价" width="120">
              <template #default="{ row }">
                ¥{{ row.product_price.toFixed(2) }}
              </template>
            </el-table-column>
            <el-table-column prop="quantity" label="数量" width="100" />
            <el-table-column prop="subtotal" label="小计" width="120">
              <template #default="{ row }">
                ¥{{ row.subtotal.toFixed(2) }}
              </template>
            </el-table-column>
          </el-table>
        </div>

        <div class="actions" style="margin-top: 20px">
          <el-button
            v-if="order.status === 'pending'"
            type="success"
            @click="handleUpdateStatus('paid')"
          >
            标记已支付
          </el-button>
          <el-button
            v-if="order.status === 'paid'"
            type="warning"
            @click="handleUpdateStatus('shipped')"
          >
            标记已发货
          </el-button>
          <el-button
            v-if="order.status === 'shipped'"
            type="primary"
            @click="handleUpdateStatus('completed')"
          >
            标记已完成
          </el-button>
          <el-button
            v-if="['pending', 'paid'].includes(order.status)"
            type="danger"
            @click="handleUpdateStatus('cancelled')"
          >
            取消订单
          </el-button>
          <el-button
            v-if="['pending', 'paid'].includes(order.status) && order.order_items && order.order_items.length > 1"
            type="primary"
            plain
            @click="openSplitDialog"
          >
            拆分订单
          </el-button>
          <el-button
            v-if="order.status === 'pending' && order.user_id"
            type="success"
            plain
            @click="openMergeDialog"
          >
            合并订单
          </el-button>
        </div>
      </div>
    </el-card>

    <el-dialog v-model="splitDialogVisible" title="拆分订单" width="700px" :close-on-click-modal="false">
      <div class="split-wizard">
        <el-alert type="info" :closable="false" style="margin-bottom: 16px">
          选择要拆分到新订单的商品行，未被选中的商品行将保留在另一个新订单中。原订单将作废。
        </el-alert>

        <el-table
          ref="splitTableRef"
          :data="order?.order_items || []"
          border
          @selection-change="handleSplitSelectionChange"
        >
          <el-table-column type="selection" width="55" />
          <el-table-column prop="product_name" label="商品名称" />
          <el-table-column prop="product_sku" label="SKU" width="140" />
          <el-table-column prop="product_price" label="单价" width="100">
            <template #default="{ row }">
              ¥{{ row.product_price.toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column prop="quantity" label="数量" width="80" />
          <el-table-column prop="subtotal" label="小计" width="100">
            <template #default="{ row }">
              ¥{{ row.subtotal.toFixed(2) }}
            </template>
          </el-table-column>
        </el-table>

        <div v-if="splitSelectedItems.length > 0" class="split-preview" style="margin-top: 16px">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-card shadow="never" class="preview-card">
                <template #header><span style="font-weight: 600">新订单 A（已选商品）</span></template>
                <div v-for="item in splitSelectedItems" :key="item.id" class="preview-item">
                  {{ item.product_name }} × {{ item.quantity }} = ¥{{ item.subtotal.toFixed(2) }}
                </div>
                <div class="preview-total">合计：¥{{ splitSelectedTotal.toFixed(2) }}</div>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never" class="preview-card">
                <template #header><span style="font-weight: 600">新订单 B（剩余商品）</span></template>
                <div v-for="item in splitUnselectedItems" :key="item.id" class="preview-item">
                  {{ item.product_name }} × {{ item.quantity }} = ¥{{ item.subtotal.toFixed(2) }}
                </div>
                <div class="preview-total">合计：¥{{ splitUnselectedTotal.toFixed(2) }}</div>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </div>

      <template #footer>
        <el-button @click="splitDialogVisible = false">取消</el-button>
        <el-button
          type="primary"
          :disabled="splitSelectedItems.length === 0 || splitSelectedItems.length >= (order?.order_items?.length || 0)"
          :loading="splitLoading"
          @click="handleSplit"
        >
          确认拆分
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="mergeDialogVisible" title="合并订单" width="700px" :close-on-click-modal="false">
      <div class="merge-wizard">
        <el-alert type="info" :closable="false" style="margin-bottom: 16px">
          选择一个与当前订单同客户的「待支付」订单进行合并。两个原订单将作废，生成一个新订单。
        </el-alert>

        <div style="margin-bottom: 16px">
          <strong>当前订单：</strong>
          <span>{{ order?.order_no }}</span>
          <span style="margin-left: 12px; color: #909399">¥{{ order?.final_amount?.toFixed(2) }}</span>
        </div>

        <el-table
          v-loading="mergeCandidatesLoading"
          :data="mergeCandidates"
          border
          highlight-current-row
          @current-change="handleMergeCandidateSelect"
        >
          <el-table-column width="55">
            <template #default="{ row }">
              <el-radio v-model="selectedMergeOrderId" :value="row.id">&nbsp;</el-radio>
            </template>
          </el-table-column>
          <el-table-column prop="order_no" label="订单号" width="180" />
          <el-table-column prop="total_amount" label="金额" width="120">
            <template #default="{ row }">
              ¥{{ row.total_amount.toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="180" />
          <el-table-column label="商品">
            <template #default="{ row }">
              {{ row.order_items?.map(i => i.product_name).join('、') || '-' }}
            </template>
          </el-table-column>
        </el-table>

        <div v-if="selectedMergeOrder" style="margin-top: 16px">
          <el-card shadow="never" class="preview-card">
            <template #header><span style="font-weight: 600">合并后预览</span></template>
            <div class="preview-item">
              订单号1：{{ order?.order_no }}（¥{{ order?.final_amount?.toFixed(2) }}）
            </div>
            <div class="preview-item">
              订单号2：{{ selectedMergeOrder.order_no }}（¥{{ selectedMergeOrder.final_amount.toFixed(2) }}）
            </div>
            <div class="preview-total">
              合并后合计：¥{{ (order?.final_amount + selectedMergeOrder.final_amount).toFixed(2) }}
            </div>
          </el-card>
        </div>
      </div>

      <template #footer>
        <el-button @click="mergeDialogVisible = false">取消</el-button>
        <el-button
          type="primary"
          :disabled="!selectedMergeOrderId"
          :loading="mergeLoading"
          @click="handleMerge"
        >
          确认合并
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { orderApi } from '@/api/modules/order'

const route = useRoute()
const router = useRouter()
const order = ref(null)
const loading = ref(false)

const splitDialogVisible = ref(false)
const splitSelectedItems = ref([])
const splitLoading = ref(false)
const splitTableRef = ref(null)

const mergeDialogVisible = ref(false)
const mergeCandidates = ref([])
const mergeCandidatesLoading = ref(false)
const selectedMergeOrderId = ref(null)
const selectedMergeOrder = ref(null)
const mergeLoading = ref(false)

const getStatusType = (status) => {
  const map = {
    pending: 'warning',
    paid: 'info',
    shipped: '',
    completed: 'success',
    cancelled: 'danger',
  }
  return map[status] || ''
}

const getStatusText = (status) => {
  const map = {
    pending: '待支付',
    paid: '已支付',
    shipped: '已发货',
    completed: '已完成',
    cancelled: '已取消',
  }
  return map[status] || status
}

const splitSelectedTotal = computed(() =>
  splitSelectedItems.value.reduce((sum, item) => sum + item.subtotal, 0)
)

const splitUnselectedItems = computed(() => {
  if (!order.value?.order_items) return []
  const selectedIds = new Set(splitSelectedItems.value.map(i => i.id))
  return order.value.order_items.filter(i => !selectedIds.has(i.id))
})

const splitUnselectedTotal = computed(() =>
  splitUnselectedItems.value.reduce((sum, item) => sum + item.subtotal, 0)
)

const fetchOrder = async () => {
  loading.value = true
  try {
    const res = await orderApi.getOrder(route.params.id)
    order.value = res.data
  } catch (error) {
    ElMessage.error('获取订单详情失败')
    router.back()
  } finally {
    loading.value = false
  }
}

const handleUpdateStatus = async (status) => {
  try {
    const statusText = getStatusText(status)
    await ElMessageBox.confirm(`确定要将订单状态更新为"${statusText}"吗？`, '提示', {
      type: 'warning',
    })
    await orderApi.updateOrderStatus(order.value.id, status)
    ElMessage.success('订单状态更新成功')
    fetchOrder()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('订单状态更新失败')
    }
  }
}

const openSplitDialog = () => {
  splitSelectedItems.value = []
  splitDialogVisible.value = true
}

const handleSplitSelectionChange = (selection) => {
  splitSelectedItems.value = selection
}

const handleSplit = async () => {
  if (splitSelectedItems.value.length === 0) {
    ElMessage.warning('请至少选择一个商品行进行拆分')
    return
  }
  if (splitSelectedItems.value.length >= (order.value?.order_items?.length || 0)) {
    ElMessage.warning('不能选择全部商品行，至少保留一个在另一个订单中')
    return
  }

  try {
    await ElMessageBox.confirm(
      '拆分后原订单将作废，生成两个新订单。确认拆分？',
      '确认拆分',
      { type: 'warning' }
    )
  } catch {
    return
  }

  splitLoading.value = true
  try {
    const itemIds = splitSelectedItems.value.map(i => i.id)
    const res = await orderApi.splitOrder(order.value.id, itemIds)
    ElMessage.success(
      `订单拆分成功！新订单A：${res.data.order_1.order_no}，新订单B：${res.data.order_2.order_no}`
    )
    splitDialogVisible.value = false
    router.push('/orders')
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '订单拆分失败')
  } finally {
    splitLoading.value = false
  }
}

const openMergeDialog = async () => {
  selectedMergeOrderId.value = null
  selectedMergeOrder.value = null
  mergeCandidates.value = []
  mergeDialogVisible.value = true

  mergeCandidatesLoading.value = true
  try {
    const res = await orderApi.getMergeCandidates(order.value.id)
    mergeCandidates.value = res.data || []
    if (mergeCandidates.value.length === 0) {
      ElMessage.info('当前没有可合并的待支付订单')
    }
  } catch (error) {
    ElMessage.error('获取可合并订单失败')
  } finally {
    mergeCandidatesLoading.value = false
  }
}

const handleMergeCandidateSelect = (row) => {
  selectedMergeOrderId.value = row?.id || null
  selectedMergeOrder.value = row || null
}

const handleMerge = async () => {
  if (!selectedMergeOrderId.value) {
    ElMessage.warning('请选择要合并的订单')
    return
  }

  try {
    await ElMessageBox.confirm(
      '合并后两个原订单将作废，生成一个新订单。确认合并？',
      '确认合并',
      { type: 'warning' }
    )
  } catch {
    return
  }

  mergeLoading.value = true
  try {
    const res = await orderApi.mergeOrders(order.value.id, selectedMergeOrderId.value)
    ElMessage.success(`订单合并成功！新订单：${res.data.order_no}`)
    mergeDialogVisible.value = false
    router.push(`/orders/${res.data.id}`)
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '订单合并失败')
  } finally {
    mergeLoading.value = false
  }
}

onMounted(() => {
  fetchOrder()
})
</script>

<style scoped>
.order-detail {
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

.final-amount {
  font-size: 18px;
  font-weight: bold;
  color: #f56c6c;
}

.actions {
  text-align: right;
}

.preview-card {
  background: #f9fafb;
}

.preview-item {
  padding: 4px 0;
  color: #374151;
  font-size: 13px;
}

.preview-total {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #d1d5db;
  font-weight: 600;
  color: #111827;
}
</style>
