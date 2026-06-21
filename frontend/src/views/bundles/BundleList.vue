<template>
  <div class="bundle-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">套餐列表</span>
            <span class="card-subtitle">管理商品组合套餐、设置优惠价格</span>
          </div>
          <el-button type="primary" @click="$router.push('/bundles/create')" round>
            新增套餐
          </el-button>
        </div>
      </template>

      <el-table :data="bundles" v-loading="loading" style="width: 100%">
        <el-table-column label="套餐信息" width="320">
          <template #default="{ row }">
            <div class="bundle-info">
              <div class="bundle-name">{{ row.name }}</div>
              <div class="bundle-sku">SKU: {{ row.sku }}</div>
              <div class="bundle-items-count">
                包含 <el-tag size="small" type="info">{{ row.item_count }}</el-tag> 件商品
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="价格信息" width="260">
          <template #default="{ row }">
            <div class="price-info">
              <div class="price-bundle">
                <span class="label">套餐价：</span>
                <span class="value-bundle">¥{{ row.total_price.toFixed(2) }}</span>
              </div>
              <div class="price-original">
                <span class="label">原价：</span>
                <span class="value-original">¥{{ row.original_total.toFixed(2) }}</span>
              </div>
              <div class="price-discount">
                <el-tag size="small" type="danger" effect="light">
                  省 ¥{{ row.discount_amount.toFixed(2) }} ({{ row.discount_percent }}%)
                </el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="包含商品" min-width="300">
          <template #default="{ row }">
            <div class="bundle-items-list" v-if="row.bundle_items">
              <div
                v-for="item in row.bundle_items"
                :key="item.id"
                class="bundle-item-row"
              >
                <span class="item-name">{{ item.product?.name || '-' }}</span>
                <span class="item-qty">x{{ item.quantity }}</span>
                <span class="item-price">¥{{ (item.product?.price || 0).toFixed(2) }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="warning" @click="handleToggle(row)">
              {{ row.status === 'active' ? '下架' : '上架' }}
            </el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
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
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { bundleApi } from '@/api/modules/bundle'

const router = useRouter()
const bundles = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)

const getStatusType = (status) => {
  const map = {
    active: 'success',
    inactive: 'info',
  }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = {
    active: '上架',
    inactive: '下架',
  }
  return map[status] || status
}

const fetchBundles = async () => {
  loading.value = true
  try {
    const res = await bundleApi.getBundles({
      page: currentPage.value,
      per_page: pageSize.value,
    })
    bundles.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取套餐列表失败')
  } finally {
    loading.value = false
  }
}

const handleEdit = (row) => {
  router.push(`/bundles/${row.id}/edit`)
}

const handleToggle = async (row) => {
  try {
    await bundleApi.toggleBundle(row.id)
    ElMessage.success(row.status === 'active' ? '已下架' : '已上架')
    fetchBundles()
  } catch (error) {
    ElMessage.error('操作失败')
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该套餐吗？', '提示', {
      type: 'warning',
    })
    await bundleApi.deleteBundle(row.id)
    ElMessage.success('删除成功')
    fetchBundles()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}

const handleSizeChange = () => {
  fetchBundles()
}

const handleCurrentChange = () => {
  fetchBundles()
}

onMounted(() => {
  fetchBundles()
})
</script>

<style scoped>
.bundle-list {
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

.bundle-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bundle-name {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.bundle-sku {
  font-size: 12px;
  color: #9ca3af;
}

.bundle-items-count {
  font-size: 12px;
  color: #6b7280;
}

.price-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.price-bundle,
.price-original {
  display: flex;
  align-items: center;
  gap: 4px;
}

.label {
  font-size: 12px;
  color: #9ca3af;
  min-width: 48px;
}

.value-bundle {
  font-size: 16px;
  font-weight: 700;
  color: #ef4444;
}

.value-original {
  font-size: 13px;
  color: #9ca3af;
  text-decoration: line-through;
}

.price-discount {
  margin-top: 2px;
}

.bundle-items-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bundle-item-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.item-name {
  flex: 1;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-qty {
  color: #6b7280;
  min-width: 30px;
}

.item-price {
  color: #6366f1;
  font-weight: 500;
  min-width: 60px;
  text-align: right;
}
</style>
