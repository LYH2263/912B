<template>
  <div class="flash-sale-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">秒杀活动</span>
            <span class="card-subtitle">创建与管理限时秒杀活动</span>
          </div>
          <div class="card-header-actions">
            <el-button @click="$router.push('/flash-sale')" round>秒杀专区</el-button>
            <el-button type="primary" @click="$router.push('/flash-sales/create')" round>
              新建活动
            </el-button>
          </div>
        </div>
      </template>

      <div class="filter-bar">
        <el-select v-model="filterStatus" placeholder="活动状态" clearable style="width: 140px" @change="handleFilter">
          <el-option label="未开始" value="pending" />
          <el-option label="进行中" value="active" />
          <el-option label="已结束" value="ended" />
        </el-select>
      </div>

      <el-table :data="flashSales" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="活动名称" width="180" />
        <el-table-column label="商品" width="160">
          <template #default="{ row }">
            {{ row.product?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="秒杀价" width="100">
          <template #default="{ row }">
            <span class="price-text">¥{{ row.flash_price }}</span>
          </template>
        </el-table-column>
        <el-table-column label="原价" width="100">
          <template #default="{ row }">
            <span class="original-price">¥{{ row.product?.price || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="活动库存" width="100">
          <template #default="{ row }">
            {{ row.remaining_stock }} / {{ row.activity_stock }}
          </template>
        </el-table-column>
        <el-table-column label="活动时间" width="200">
          <template #default="{ row }">
            <div class="time-range">
              <div>{{ row.start_time }}</div>
              <div>{{ row.end_time }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)" :disabled="row.status === 'active'">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)" :disabled="row.status === 'active'">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchFlashSales"
        @current-change="fetchFlashSales"
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { flashSaleApi } from '@/api/modules/flashSale'

const router = useRouter()
const flashSales = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const filterStatus = ref('')

const getStatusType = (status) => {
  const map = { pending: 'warning', active: 'success', ended: 'info' }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = { pending: '未开始', active: '进行中', ended: '已结束' }
  return map[status] || status
}

const fetchFlashSales = async () => {
  loading.value = true
  try {
    const res = await flashSaleApi.getFlashSales({
      page: currentPage.value,
      per_page: pageSize.value,
      status: filterStatus.value || undefined,
    })
    flashSales.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取秒杀活动列表失败')
  } finally {
    loading.value = false
  }
}

const handleFilter = () => {
  currentPage.value = 1
  fetchFlashSales()
}

const handleEdit = (row) => {
  router.push(`/flash-sales/${row.id}/edit`)
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该秒杀活动吗？', '提示', { type: 'warning' })
    await flashSaleApi.deleteFlashSale(row.id)
    ElMessage.success('删除成功')
    fetchFlashSales()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

onMounted(() => {
  fetchFlashSales()
})
</script>

<style scoped>
.flash-sale-list {
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

.card-header-actions {
  display: flex;
  gap: 8px;
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

.filter-bar {
  margin-bottom: 16px;
  display: flex;
  gap: 12px;
}

.price-text {
  color: #f56c6c;
  font-weight: 600;
}

.original-price {
  color: #9ca3af;
  text-decoration: line-through;
  font-size: 13px;
}

.time-range {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.6;
}
</style>
