<template>
  <div class="report-builder page-shell">
    <div class="report-header">
      <div>
        <h2 class="page-title">自定义报表生成器</h2>
        <p class="page-subtitle">灵活选择维度与指标，快速生成销售报表并支持导出</p>
      </div>
    </div>

    <el-card class="preset-card">
      <div class="preset-title">
        <el-icon :size="18"><MagicStick /></el-icon>
        <span>预设模板</span>
      </div>
      <div class="preset-list">
        <div
          v-for="template in templates"
          :key="template.id"
          class="preset-item"
          :class="{ active: activeTemplate === template.id }"
          @click="applyTemplate(template)"
        >
          <div class="preset-icon">
            <el-icon :size="24"><component :is="templateIcons[template.id]" /></el-icon>
          </div>
          <div class="preset-info">
            <div class="preset-name">{{ template.name }}</div>
            <div class="preset-desc">{{ template.description }}</div>
          </div>
        </div>
      </div>
    </el-card>

    <el-card class="config-card">
      <div class="config-title">
        <el-icon :size="18"><Setting /></el-icon>
        <span>报表配置</span>
      </div>

      <el-form :model="form" label-width="100px" class="config-form">
        <el-row :gutter="24">
          <el-col :span="8">
            <el-form-item label="报表维度">
              <el-select v-model="form.dimension" placeholder="请选择维度">
                <el-option
                  v-for="(label, key) in dimensions"
                  :key="key"
                  :label="label"
                  :value="key"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="统计指标">
              <el-checkbox-group v-model="form.metrics">
                <el-checkbox
                  v-for="(label, key) in metrics"
                  :key="key"
                  :label="key"
                >
                  {{ label }}
                </el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="时间范围">
              <el-date-picker
                v-model="form.dateRange"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <div class="form-actions">
          <el-button type="primary" :icon="RefreshRight" :loading="loading" @click="generateReport">
            生成报表
          </el-button>
          <el-button
            type="success"
            :icon="Download"
            :disabled="!reportData"
            @click="exportCsv"
          >
            导出 CSV
          </el-button>
          <el-button :icon="RefreshLeft" @click="resetForm">
            重置
          </el-button>
        </div>
      </el-form>
    </el-card>

    <el-card v-if="reportData" class="result-card">
      <div class="result-header">
        <div class="result-title">
          <el-icon :size="18"><DataLine /></el-icon>
          <span>报表预览</span>
          <el-tag type="info" size="small">{{ reportData.row_count }} 条记录</el-tag>
        </div>
        <div class="result-range">
          {{ reportData.start_date }} ~ {{ reportData.end_date }}
        </div>
      </div>

      <div class="summary-row">
        <div
          v-for="(total, key) in reportData.totals"
          :key="key"
          class="summary-item"
        >
          <div class="summary-label">{{ metricLabels[key] || key }}</div>
          <div class="summary-value">
            {{ formatMetric(key, total) }}
          </div>
        </div>
      </div>

      <el-table :data="reportData.rows" stripe border class="result-table">
        <el-table-column
          v-for="col in reportData.columns"
          :key="col.key"
          :prop="col.key"
          :label="col.label"
          align="center"
        >
          <template #default="scope">
            <template v-if="col.type === 'decimal'">
              ¥{{ Number(scope.row[col.key]).toFixed(2) }}
            </template>
            <template v-else-if="col.type === 'integer'">
              {{ Number(scope.row[col.key]).toLocaleString() }}
            </template>
            <template v-else>
              {{ scope.row[col.key] }}
            </template>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card v-else class="empty-card">
      <el-empty description="请选择维度和指标，点击生成报表查看结果">
        <template #image>
          <el-icon :size="80" class="empty-icon"><DataAnalysis /></el-icon>
        </template>
      </el-empty>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  Setting,
  RefreshRight,
  Download,
  RefreshLeft,
  DataLine,
  MagicStick,
  DataAnalysis,
  Goods,
  TrendCharts,
} from '@element-plus/icons-vue'
import { reportApi } from '@/api/modules/report'
import dayjs from 'dayjs'

const templateIcons = {
  daily_sales: TrendCharts,
  category_sales: Goods,
  product_top_sales: DataLine,
}

const templates = ref([])
const dimensions = ref({})
const metrics = ref({})

const loading = ref(false)
const reportData = ref(null)
const activeTemplate = ref(null)

const form = reactive({
  dimension: 'day',
  metrics: ['order_count', 'sales_amount'],
  dateRange: [],
})

const metricLabels = computed(() => {
  return reportData.value?.metric_labels || {}
})

const formatMetric = (key, value) => {
  if (key === 'order_count') {
    return Number(value).toLocaleString()
  }
  return `¥${Number(value).toFixed(2)}`
}

const fetchOptions = async () => {
  try {
    const res = await reportApi.getOptions()
    dimensions.value = res.data.dimensions
    metrics.value = res.data.metrics
  } catch (e) {
    console.error('获取选项失败', e)
  }
}

const fetchTemplates = async () => {
  try {
    const res = await reportApi.getTemplates()
    templates.value = res.data || []
  } catch (e) {
    console.error('获取模板失败', e)
  }
}

const applyTemplate = (template) => {
  activeTemplate.value = template.id
  form.dimension = template.dimension
  form.metrics = [...template.metrics]

  const days = template.default_days || 30
  const endDate = dayjs().format('YYYY-MM-DD')
  const startDate = dayjs().subtract(days, 'day').format('YYYY-MM-DD')
  form.dateRange = [startDate, endDate]

  ElMessage.success(`已加载模板：${template.name}`)
}

const generateReport = async () => {
  if (!form.dimension) {
    return ElMessage.warning('请选择报表维度')
  }
  if (!form.metrics || form.metrics.length === 0) {
    return ElMessage.warning('请至少选择一个指标')
  }
  if (!form.dateRange || form.dateRange.length !== 2) {
    return ElMessage.warning('请选择时间范围')
  }

  loading.value = true
  activeTemplate.value = null

  try {
    const res = await reportApi.generateReport({
      dimension: form.dimension,
      metrics: form.metrics,
      start_date: form.dateRange[0],
      end_date: form.dateRange[1],
    })

    reportData.value = res.data
    ElMessage.success('报表生成成功')
  } catch (e) {
    console.error('生成报表失败', e)
    ElMessage.error('生成报表失败')
  } finally {
    loading.value = false
  }
}

const exportCsv = async () => {
  if (!reportData.value) return

  try {
    const res = await reportApi.exportCsv({
      dimension: form.dimension,
      metrics: form.metrics,
      start_date: form.dateRange[0],
      end_date: form.dateRange[1],
    })

    const blob = new Blob([res], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `report_${dayjs().format('YYYYMMDD_HHmmss')}.csv`
    link.click()
    URL.revokeObjectURL(url)

    ElMessage.success('导出成功')
  } catch (e) {
    console.error('导出失败', e)
    ElMessage.error('导出失败')
  }
}

const resetForm = () => {
  form.dimension = 'day'
  form.metrics = ['order_count', 'sales_amount']
  form.dateRange = []
  reportData.value = null
  activeTemplate.value = null
}

onMounted(() => {
  fetchOptions()
  fetchTemplates()
})
</script>

<style scoped>
.report-builder {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1f2933;
  letter-spacing: 0.02em;
  margin: 0;
}

.page-subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: #6b7280;
}

.preset-card,
.config-card,
.result-card,
.empty-card {
  border-radius: 16px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 12px 32px rgba(148, 163, 184, 0.35);
}

.preset-title,
.config-title,
.result-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #1f2933;
  margin-bottom: 16px;
}

.preset-list {
  display: flex;
  gap: 16px;
}

.preset-item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 12px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.25s ease;
}

.preset-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(148, 163, 184, 0.35);
}

.preset-item.active {
  border-color: #4f46e5;
  background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
}

.preset-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.preset-item.active .preset-icon {
  background: linear-gradient(135deg, #4f46e5, #4338ca);
}

.preset-info {
  flex: 1;
  min-width: 0;
}

.preset-name {
  font-size: 14px;
  font-weight: 600;
  color: #1f2933;
}

.preset-desc {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.config-form {
  margin-top: 8px;
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.result-title {
  margin-bottom: 0;
  gap: 10px;
}

.result-range {
  font-size: 13px;
  color: #6b7280;
}

.summary-row {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
  padding: 16px;
  border-radius: 12px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.summary-item {
  flex: 1;
  text-align: center;
  padding: 12px;
  border-radius: 10px;
  background: white;
  box-shadow: 0 2px 8px rgba(148, 163, 184, 0.15);
}

.summary-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}

.summary-value {
  font-size: 22px;
  font-weight: 700;
  color: #1f2933;
}

.result-table {
  margin-top: 8px;
}

.empty-card {
  min-height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-icon {
  color: #d1d5db;
}
</style>
