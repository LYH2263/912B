<template>
  <div class="point-log-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">积分流水</span>
            <span class="card-subtitle">查询积分获得与消费的详细记录</span>
          </div>
          <div class="header-summary">
            <div class="summary-item">
              <span class="summary-label">当前积分</span>
              <span class="summary-value points">{{ memberInfo?.points ?? 0 }}</span>
            </div>
            <div class="summary-divider" />
            <div class="summary-item">
              <span class="summary-label">会员等级</span>
              <span class="summary-value level">{{ memberInfo?.level_label ?? '普通会员' }}</span>
            </div>
          </div>
        </div>
      </template>

      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="类型">
          <el-select v-model="filters.type" placeholder="全部类型" clearable style="width: 140px">
            <el-option label="获得" value="earn" />
            <el-option label="消费" value="spend" />
            <el-option label="退回" value="refund" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始日期">
          <el-date-picker
            v-model="filters.start_date"
            type="date"
            placeholder="选择日期"
            value-format="YYYY-MM-DD"
            style="width: 160px"
          />
        </el-form-item>
        <el-form-item label="结束日期">
          <el-date-picker
            v-model="filters.end_date"
            type="date"
            placeholder="选择日期"
            value-format="YYYY-MM-DD"
            style="width: 160px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="logs" v-loading="loading" style="width: 100%">
        <el-table-column label="时间" prop="created_at" width="180" />
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)">
              {{ row.type_label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="积分变动" width="120">
          <template #default="{ row }">
            <span :class="row.points >= 0 ? 'points-earn' : 'points-spend'">
              {{ row.points >= 0 ? '+' : '' }}{{ row.points }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="变动后余额" prop="balance_after" width="120" />
        <el-table-column label="说明" prop="description" />
        <el-table-column label="关联订单" width="180">
          <template #default="{ row }">
            <span v-if="row.related_order_no">{{ row.related_order_no }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchLogs"
        @current-change="fetchLogs"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { memberApi } from '@/api/modules/member'

const logs = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const memberInfo = ref(null)

const filters = reactive({
  type: '',
  start_date: '',
  end_date: '',
})

const getTypeTagType = (type) => {
  const map = {
    earn: 'success',
    spend: 'warning',
    refund: 'info',
  }
  return map[type] || 'info'
}

const fetchMemberInfo = async () => {
  try {
    const res = await memberApi.getMemberInfo()
    memberInfo.value = res.data
  } catch (error) {
    console.error('获取会员信息失败', error)
  }
}

const fetchLogs = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
    }
    if (filters.type) params.type = filters.type
    if (filters.start_date) params.start_date = filters.start_date
    if (filters.end_date) params.end_date = filters.end_date

    const res = await memberApi.getPointLogs(params)
    logs.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取积分流水失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  fetchLogs()
}

const handleReset = () => {
  Object.assign(filters, {
    type: '',
    start_date: '',
    end_date: '',
  })
  handleSearch()
}

onMounted(() => {
  fetchMemberInfo()
  fetchLogs()
})
</script>

<style scoped>
.point-log-list {
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

.header-summary {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 8px 16px;
  background: linear-gradient(135deg, #f5f7ff 0%, #eef3ff 100%);
  border-radius: 10px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
}

.summary-label {
  font-size: 11px;
  color: #6b7280;
}

.summary-value {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.summary-value.points {
  color: #e6a23c;
}

.summary-value.level {
  color: #409eff;
}

.summary-divider {
  width: 1px;
  height: 32px;
  background: #dcdfe6;
}

.filter-form {
  margin-bottom: 20px;
}

.points-earn {
  color: #67c23a;
  font-weight: 600;
}

.points-spend {
  color: #f56c6c;
  font-weight: 600;
}

.text-muted {
  color: #c0c4cc;
}
</style>
