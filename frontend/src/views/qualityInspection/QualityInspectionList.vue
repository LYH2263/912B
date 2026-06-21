<template>
  <div class="quality-inspection-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">质检记录列表</span>
            <span class="card-subtitle">查看与管理商品入库质检记录、合格率统计</span>
          </div>
          <el-button type="primary" @click="$router.push('/quality-inspections/create')" round>
            新建质检记录
          </el-button>
        </div>
      </template>

      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="批次号">
          <el-input v-model="filters.batch_no" placeholder="请输入批次号" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item label="质检员">
          <el-input v-model="filters.inspector" placeholder="请输入质检员" clearable style="width: 160px" />
        </el-form-item>
        <el-form-item label="仅含不合格">
          <el-switch v-model="hasUnqualified" @change="handleUnqualifiedChange" />
        </el-form-item>
        <el-form-item label="质检日期">
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

      <el-table :data="inspections" v-loading="loading" style="width: 100%">
        <el-table-column prop="batch_no" label="批次号" width="180" />
        <el-table-column label="商品" min-width="180">
          <template #default="{ row }">
            <div class="product-cell">
              <span v-if="row.product">{{ row.product.name }}</span>
              <span v-if="row.product" class="product-sku">({{ row.product.sku }})</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="关联采购单" width="180">
          <template #default="{ row }">
            <span v-if="row.purchase_order">
              {{ row.purchase_order.purchase_order_no }}
            </span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="qualified_quantity" label="合格数量" width="100" align="center">
          <template #default="{ row }">
            <span class="text-success">{{ row.qualified_quantity }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="unqualified_quantity" label="不合格数量" width="100" align="center">
          <template #default="{ row }">
            <span :class="{ 'text-danger': row.unqualified_quantity > 0 }">
              {{ row.unqualified_quantity }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="total_quantity" label="总数" width="80" align="center" />
        <el-table-column prop="pass_rate" label="合格率" width="100" align="center">
          <template #default="{ row }">
            <el-progress
              :percentage="row.pass_rate"
              :color="getPassRateColor(row.pass_rate)"
              :stroke-width="8"
            />
          </template>
        </el-table-column>
        <el-table-column prop="inspector" label="质检员" width="100" />
        <el-table-column prop="inspection_date" label="质检日期" width="120" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <div class="action-buttons">
              <el-button size="small" @click="handleView(row)">查看</el-button>
              <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog v-model="detailVisible" title="质检记录详情" width="800px" top="5vh">
      <div v-if="currentDetail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="批次号">
            {{ currentDetail.batch_no }}
          </el-descriptions-item>
          <el-descriptions-item label="质检日期">
            {{ currentDetail.inspection_date }}
          </el-descriptions-item>
          <el-descriptions-item label="商品">
            {{ currentDetail.product?.name || '-' }} ({{ currentDetail.product?.sku || '-' }})
          </el-descriptions-item>
          <el-descriptions-item label="质检员">
            {{ currentDetail.inspector }}
          </el-descriptions-item>
          <el-descriptions-item label="关联采购单">
            {{ currentDetail.purchase_order?.purchase_order_no || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="创建人">
            {{ currentDetail.created_by?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="合格数量">
            <span class="text-success">{{ currentDetail.qualified_quantity }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="不合格数量">
            <span :class="{ 'text-danger': currentDetail.unqualified_quantity > 0 }">
              {{ currentDetail.unqualified_quantity }}
            </span>
          </el-descriptions-item>
          <el-descriptions-item label="合格率">
            <el-tag :type="getPassRateTagType(currentDetail.pass_rate)">
              {{ currentDetail.pass_rate }}%
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="总数量">
            {{ currentDetail.total_quantity }}
          </el-descriptions-item>
          <el-descriptions-item label="不合格原因" :span="2">
            <span v-if="currentDetail.unqualified_reason">{{ currentDetail.unqualified_reason }}</span>
            <span v-else class="text-muted">-</span>
          </el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">
            <span v-if="currentDetail.remark">{{ currentDetail.remark }}</span>
            <span v-else class="text-muted">-</span>
          </el-descriptions-item>
        </el-descriptions>
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
import { qualityInspectionApi } from '@/api/modules/qualityInspection'

const router = useRouter()
const inspections = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const dateRange = ref(null)
const hasUnqualified = ref(false)
const detailVisible = ref(false)
const currentDetail = ref(null)

const filters = reactive({
  batch_no: '',
  inspector: '',
  start_date: '',
  end_date: '',
})

const getPassRateColor = (rate) => {
  if (rate >= 95) return '#67c23a'
  if (rate >= 80) return '#e6a23c'
  return '#f56c6c'
}

const getPassRateTagType = (rate) => {
  if (rate >= 95) return 'success'
  if (rate >= 80) return 'warning'
  return 'danger'
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

const handleUnqualifiedChange = (val) => {
  if (val) {
    filters.has_unqualified = 'true'
  } else {
    delete filters.has_unqualified
  }
}

const handleSearch = () => {
  currentPage.value = 1
  fetchInspections()
}

const handleReset = () => {
  Object.assign(filters, {
    batch_no: '',
    inspector: '',
    start_date: '',
    end_date: '',
  })
  delete filters.has_unqualified
  hasUnqualified.value = false
  dateRange.value = null
  handleSearch()
}

const fetchInspections = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
      ...filters,
    }
    const res = await qualityInspectionApi.getQualityInspections(params)
    inspections.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取质检记录列表失败')
  } finally {
    loading.value = false
  }
}

const handleView = async (row) => {
  try {
    const res = await qualityInspectionApi.getQualityInspection(row.id)
    currentDetail.value = res.data
    detailVisible.value = true
  } catch (error) {
    ElMessage.error('获取质检记录详情失败')
  }
}

const handleEdit = (row) => {
  router.push(`/quality-inspections/${row.id}/edit`)
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      '确定要删除该质检记录吗？删除后已入库的合格数量将从库存中扣除，此操作不可恢复。',
      '提示',
      {
        type: 'danger',
      }
    )
    await qualityInspectionApi.deleteQualityInspection(row.id)
    ElMessage.success('质检记录删除成功')
    fetchInspections()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '质检记录删除失败')
    }
  }
}

const handleSizeChange = () => {
  fetchInspections()
}

const handleCurrentChange = () => {
  fetchInspections()
}

onMounted(() => {
  fetchInspections()
})
</script>

<style scoped>
.quality-inspection-list {
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
}

.product-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.product-sku {
  font-size: 12px;
  color: #9ca3af;
}

.text-success {
  color: #67c23a;
  font-weight: 500;
}

.text-danger {
  color: #f56c6c;
  font-weight: 500;
}

.text-muted {
  color: #9ca3af;
}
</style>
