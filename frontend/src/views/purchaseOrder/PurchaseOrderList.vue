<template>
  <div class="purchase-order-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">采购单列表</span>
            <span class="card-subtitle">查看与管理采购单状态、供应商与入库进度</span>
          </div>
          <el-button type="primary" @click="$router.push('/purchase-orders/create')" round>
            创建采购单
          </el-button>
        </div>
      </template>

      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="采购单号">
          <el-input v-model="filters.purchase_order_no" placeholder="请输入采购单号" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item label="供应商">
          <el-input v-model="filters.supplier_name" placeholder="请输入供应商名称" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item label="采购状态">
          <el-select v-model="filters.status" placeholder="请选择状态" clearable style="width: 140px">
            <el-option label="草稿" value="draft" />
            <el-option label="待入库" value="pending" />
            <el-option label="部分入库" value="partial" />
            <el-option label="已完成" value="completed" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            @change="handleDateChange"
            style="width: 260px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="purchaseOrders" v-loading="loading" style="width: 100%">
        <el-table-column prop="purchase_order_no" label="采购单号" width="180" />
        <el-table-column prop="supplier_name" label="供应商" width="160" />
        <el-table-column prop="total_quantity" label="采购总数" width="100" align="center" />
        <el-table-column prop="total_received_quantity" label="已入库" width="100" align="center">
          <template #default="{ row }">
            <span :class="{ 'text-success': row.is_fully_received }">
              {{ row.total_received_quantity }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="total_amount" label="采购金额" width="120">
          <template #default="{ row }">
            ¥{{ row.total_amount.toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column prop="expected_arrival_date" label="预计到货日" width="130" />
        <el-table-column prop="actual_arrival_date" label="实际到货日" width="130" />
        <el-table-column prop="status" label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="320" fixed="right">
          <template #default="{ row }">
            <div class="action-buttons">
              <el-button size="small" @click="handleView(row)">查看</el-button>
              <el-button
                v-if="row.can_submit"
                size="small"
                type="warning"
                @click="handleSubmit(row)"
              >
                提交
              </el-button>
              <el-button
                v-if="row.can_stock_in"
                size="small"
                type="success"
                @click="handleStockIn(row)"
              >
                入库
              </el-button>
              <el-button
                v-if="row.can_edit"
                size="small"
                type="primary"
                @click="handleEdit(row)"
              >
                编辑
              </el-button>
              <el-button
                v-if="row.can_delete"
                size="small"
                type="danger"
                @click="handleDelete(row)"
              >
                删除
              </el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>

    <el-dialog v-model="detailVisible" title="采购单详情" width="900px" top="5vh">
      <div v-if="currentDetail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="采购单号">
            {{ currentDetail.purchase_order_no }}
          </el-descriptions-item>
          <el-descriptions-item label="供应商">
            {{ currentDetail.supplier_name }}
          </el-descriptions-item>
          <el-descriptions-item label="联系人">
            {{ currentDetail.supplier_contact || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="联系电话">
            {{ currentDetail.supplier_phone || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="预计到货日">
            {{ currentDetail.expected_arrival_date }}
          </el-descriptions-item>
          <el-descriptions-item label="实际到货日">
            {{ currentDetail.actual_arrival_date || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="采购金额">
            ¥{{ currentDetail.total_amount.toFixed(2) }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentDetail.status)">
              {{ getStatusText(currentDetail.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="创建人">
            {{ currentDetail.created_by?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="入库人">
            {{ currentDetail.stock_in_by?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">
            {{ currentDetail.remark || '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">商品明细</el-divider>
        <el-table :data="currentDetail.items" border style="width: 100%">
          <el-table-column prop="product_sku" label="商品SKU" width="140" />
          <el-table-column prop="product_name" label="商品名称" min-width="180" />
          <el-table-column prop="purchase_price" label="采购单价" width="120">
            <template #default="{ row }">
              ¥{{ row.purchase_price.toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column prop="quantity" label="采购数量" width="100" align="center" />
          <el-table-column prop="received_quantity" label="已入库" width="100" align="center">
            <template #default="{ row }">
              <span :class="{ 'text-success': row.is_fully_received }">
                {{ row.received_quantity }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="remaining_quantity" label="待入库" width="100" align="center">
            <template #default="{ row }">
              <span :class="{ 'text-warning': row.remaining_quantity > 0 }">
                {{ row.remaining_quantity }}
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="subtotal" label="小计" width="120">
            <template #default="{ row }">
              ¥{{ row.subtotal.toFixed(2) }}
            </template>
          </el-table-column>
        </el-table>
      </div>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { purchaseOrderApi } from '@/api/modules/purchaseOrder'

const router = useRouter()
const purchaseOrders = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const dateRange = ref(null)
const detailVisible = ref(false)
const currentDetail = ref(null)

const filters = reactive({
  purchase_order_no: '',
  supplier_name: '',
  status: '',
  start_date: '',
  end_date: '',
})

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

const handleDateChange = (dates) => {
  if (dates && dates.length === 2) {
    filters.start_date = dates[0]
    filters.end_date = dates[1]
  } else {
    filters.start_date = ''
    filters.end_date = ''
  }
}

const handleSearch = () => {
  currentPage.value = 1
  fetchPurchaseOrders()
}

const handleReset = () => {
  Object.assign(filters, {
    purchase_order_no: '',
    supplier_name: '',
    status: '',
    start_date: '',
    end_date: '',
  })
  dateRange.value = null
  handleSearch()
}

const fetchPurchaseOrders = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
      ...filters,
    }
    const res = await purchaseOrderApi.getPurchaseOrders(params)
    purchaseOrders.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取采购单列表失败')
  } finally {
    loading.value = false
  }
}

const handleView = async (row) => {
  try {
    const res = await purchaseOrderApi.getPurchaseOrder(row.id)
    currentDetail.value = res.data
    detailVisible.value = true
  } catch (error) {
    ElMessage.error('获取采购单详情失败')
  }
}

const handleEdit = (row) => {
  router.push(`/purchase-orders/${row.id}/edit`)
}

const handleSubmit = async (row) => {
  try {
    await ElMessageBox.confirm('确定要提交该采购单吗？提交后将无法修改。', '提示', {
      type: 'warning',
    })
    await purchaseOrderApi.submitPurchaseOrder(row.id)
    ElMessage.success('采购单提交成功')
    fetchPurchaseOrders()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '采购单提交失败')
    }
  }
}

const handleStockIn = (row) => {
  router.push(`/purchase-orders/${row.id}/stock-in`)
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该采购单吗？此操作不可恢复。', '提示', {
      type: 'danger',
    })
    await purchaseOrderApi.deletePurchaseOrder(row.id)
    ElMessage.success('采购单删除成功')
    fetchPurchaseOrders()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '采购单删除失败')
    }
  }
}

const handleSizeChange = () => {
  fetchPurchaseOrders()
}

const handleCurrentChange = () => {
  fetchPurchaseOrders()
}

onMounted(() => {
  fetchPurchaseOrders()
})
</script>

<style scoped>
.purchase-order-list {
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

.filter-form {
  margin-bottom: 20px;
}

.action-buttons {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 6px;
  flex-wrap: nowrap;
}

.text-success {
  color: #67c23a;
  font-weight: 500;
}

.text-warning {
  color: #e6a23c;
  font-weight: 500;
}
</style>
