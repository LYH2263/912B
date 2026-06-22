<template>
  <div class="dashboard page-shell">
    <div class="dashboard-header">
      <div>
        <h2 class="page-title">仪表盘总览</h2>
        <p class="page-subtitle">一眼掌握商品、订单与库存的核心数据</p>
      </div>
      <div v-if="member" class="member-badge-wrapper">
        <div class="member-badge" :class="`level-${member.level}`">
          <div class="badge-icon">
            <el-icon :size="28">
              <component :is="levelIcon" />
            </el-icon>
          </div>
          <div class="badge-info">
            <div class="badge-level">{{ member.level_label }}</div>
            <div class="badge-points">
              <el-icon :size="14"><Star /></el-icon>
              {{ member.points }} 积分
            </div>
          </div>
        </div>
        <div class="member-progress" v-if="member.next_threshold">
          <div class="progress-label">
            <span>累计消费 ¥{{ member.total_consumption.toFixed(2) }}</span>
            <span>距下一级 ¥{{ (member.next_threshold - member.total_consumption).toFixed(2) }}</span>
          </div>
          <el-progress
            :percentage="progressPercentage"
            :stroke-width="6"
            :show-text="false"
            :color="levelColor"
          />
        </div>
        <div class="member-progress" v-else>
          <div class="progress-label top-level">
            <el-icon :size="14"><GoldMedal /></el-icon>
            已达最高等级
          </div>
        </div>
      </div>
    </div>

    <el-row :gutter="20" class="stats-row">
      <el-col :span="6" v-for="stat in stats" :key="stat.title">
        <el-card
          class="stat-card"
          :style="{ '--accent-color': stat.color, cursor: stat.clickable ? 'pointer' : 'default' }"
          @click="stat.clickable && $router.push(stat.path)"
        >
          <div class="stat-content">
            <div class="stat-text">
              <div class="stat-label">{{ stat.title }}</div>
            <div class="stat-value">{{ stat.value }}</div>
          </div>
            <div class="stat-icon">
            <el-icon :size="40"><component :is="stat.icon" /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Goods, Document, Box, Star, GoldMedal, Medal, Trophy, ChatDotRound } from '@element-plus/icons-vue'
import { dashboardApi } from '@/api/modules/dashboard'

const stats = ref([
  { title: '商品总数', value: 0, icon: Goods, color: '#409EFF' },
  { title: '今日订单', value: 0, icon: Document, color: '#67C23A' },
  { title: '库存总价值', value: 0, icon: Box, color: '#E6A23C' },
  { title: '待处理工单', value: 0, icon: ChatDotRound, color: '#F56C6C', clickable: true, path: '/tickets' },
])

const member = ref(null)

const levelIcon = computed(() => {
  if (!member.value) return Medal
  const map = {
    normal: Medal,
    silver: Trophy,
    gold: GoldMedal,
  }
  return map[member.value.level] || Medal
})

const levelColor = computed(() => {
  if (!member.value) return '#909399'
  const map = {
    normal: '#909399',
    silver: '#C0C4CC',
    gold: '#E6A23C',
  }
  return map[member.value.level] || '#909399'
})

const progressPercentage = computed(() => {
  if (!member.value || !member.value.next_threshold) return 100
  const thresholds = { normal: 0, silver: 1000, gold: 5000 }
  const currentLevel = member.value.level
  const currentBase = thresholds[currentLevel] || 0
  const nextThreshold = member.value.next_threshold
  const progress = (member.value.total_consumption - currentBase) / (nextThreshold - currentBase)
  return Math.min(Math.round(progress * 100), 100)
})

onMounted(async () => {
  try {
    const res = await dashboardApi.getSummary()
    const data = res.data

    stats.value[0].value = data.products.total
    stats.value[1].value = data.orders.today_count
    const totalValue = Number(data.inventory?.total_value ?? 0)
    stats.value[2].value = `¥${totalValue.toFixed(2)}`
    stats.value[3].value = data.tickets?.pending ?? 0

    if (data.member) {
      member.value = data.member
    }
  } catch (error) {
    console.error('获取数据失败', error)
  }
})
</script>

<style scoped>
.dashboard {
  padding: 24px 24px 20px;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 20px;
}

.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1f2933;
  letter-spacing: 0.02em;
}

.page-subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: #6b7280;
}

.stats-row {
  margin-top: 4px;
}

.stat-card {
  position: relative;
  margin-bottom: 20px;
  border-radius: 16px;
  border: none;
  background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.96) 0%, #f5f7ff 40%, #eef3ff 100%);
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
  overflow: hidden;
  transition: transform 0.18s ease-out, box-shadow 0.18s ease-out;
}

.stat-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  border-top: 3px solid var(--accent-color);
  opacity: 0.9;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 24px 55px rgba(15, 23, 42, 0.22);
}

.stat-content {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 16px;
}

.stat-text {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-label {
  font-size: 13px;
  color: #6b7280;
}

.stat-value {
  font-size: 30px;
  font-weight: 700;
  color: #111827;
}

.stat-icon {
  color: var(--accent-color);
  opacity: 0.26;
}

.member-badge-wrapper {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.member-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 20px 10px 14px;
  border-radius: 40px;
  background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}

.member-badge.level-normal {
  background: linear-gradient(135deg, #fafafa 0%, #f0f0f0 100%);
}

.member-badge.level-silver {
  background: linear-gradient(135deg, #ffffff 0%, #e8ecf1 50%, #d4d9e0 100%);
}

.member-badge.level-gold {
  background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 50%, #ffd54f 100%);
}

.badge-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.7);
}

.level-normal .badge-icon {
  color: #909399;
}

.level-silver .badge-icon {
  color: #909399;
}

.level-gold .badge-icon {
  color: #e6a23c;
}

.badge-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.badge-level {
  font-size: 15px;
  font-weight: 600;
  color: #1f2933;
}

.level-gold .badge-level {
  color: #b8860b;
}

.badge-points {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #6b7280;
}

.level-gold .badge-points {
  color: #8b7500;
}

.member-progress {
  width: 240px;
  padding: 8px 14px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.progress-label {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 6px;
}

.progress-label.top-level {
  justify-content: center;
  align-items: center;
  gap: 4px;
  color: #e6a23c;
  font-weight: 500;
}
</style>
