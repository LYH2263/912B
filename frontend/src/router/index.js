import { createRouter, createWebHistory } from 'vue-router'

// 路由守卫
const requireAuth = (to, from, next) => {
  const token = localStorage.getItem('token')
  if (token) {
    next()
  } else {
    next('/login')
  }
}

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/auth/Login.vue'),
  },
  {
    path: '/',
    component: () => import('../components/layout/MainLayout.vue'),
    redirect: '/dashboard',
    beforeEnter: requireAuth,
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('../views/dashboard/Dashboard.vue'),
      },
      {
        path: 'products',
        name: 'Products',
        component: () => import('../views/products/ProductList.vue'),
      },
      {
        path: 'products/create',
        name: 'ProductCreate',
        component: () => import('../views/products/ProductForm.vue'),
      },
      {
        path: 'products/:id/edit',
        name: 'ProductEdit',
        component: () => import('../views/products/ProductForm.vue'),
      },
      {
        path: 'bundles',
        name: 'Bundles',
        component: () => import('../views/bundles/BundleList.vue'),
      },
      {
        path: 'bundles/create',
        name: 'BundleCreate',
        component: () => import('../views/bundles/BundleForm.vue'),
      },
      {
        path: 'bundles/:id/edit',
        name: 'BundleEdit',
        component: () => import('../views/bundles/BundleForm.vue'),
      },
      {
        path: 'orders',
        name: 'Orders',
        component: () => import('../views/orders/OrderList.vue'),
      },
      {
        path: 'orders/create',
        name: 'OrderCreate',
        component: () => import('../views/orders/OrderForm.vue'),
      },
      {
        path: 'orders/:id',
        name: 'OrderDetail',
        component: () => import('../views/orders/OrderDetail.vue'),
      },
      {
        path: 'inventory',
        name: 'Inventory',
        component: () => import('../views/inventory/InventoryList.vue'),
      },
      {
        path: 'flash-sales',
        name: 'FlashSales',
        component: () => import('../views/flashSale/FlashSaleList.vue'),
      },
      {
        path: 'flash-sales/create',
        name: 'FlashSaleCreate',
        component: () => import('../views/flashSale/FlashSaleForm.vue'),
      },
      {
        path: 'flash-sales/:id/edit',
        name: 'FlashSaleEdit',
        component: () => import('../views/flashSale/FlashSaleForm.vue'),
      },
      {
        path: 'flash-sale',
        name: 'FlashSalePage',
        component: () => import('../views/flashSale/FlashSalePage.vue'),
      },
      {
        path: 'points',
        name: 'PointLogs',
        component: () => import('../views/member/PointLogList.vue'),
      },
      {
        path: 'purchase-orders',
        name: 'PurchaseOrders',
        component: () => import('../views/purchaseOrder/PurchaseOrderList.vue'),
      },
      {
        path: 'purchase-orders/create',
        name: 'PurchaseOrderCreate',
        component: () => import('../views/purchaseOrder/PurchaseOrderForm.vue'),
      },
      {
        path: 'purchase-orders/:id/edit',
        name: 'PurchaseOrderEdit',
        component: () => import('../views/purchaseOrder/PurchaseOrderForm.vue'),
      },
      {
        path: 'purchase-orders/:id/stock-in',
        name: 'PurchaseOrderStockIn',
        component: () => import('../views/purchaseOrder/PurchaseOrderStockIn.vue'),
      },
      {
        path: 'pricing-rules',
        name: 'PricingRules',
        component: () => import('../views/pricingRules/PricingRuleList.vue'),
      },
      {
        path: 'pricing-rules/create',
        name: 'PricingRuleCreate',
        component: () => import('../views/pricingRules/PricingRuleForm.vue'),
      },
      {
        path: 'pricing-rules/:id/edit',
        name: 'PricingRuleEdit',
        component: () => import('../views/pricingRules/PricingRuleForm.vue'),
      },
      {
        path: 'tickets',
        name: 'Tickets',
        component: () => import('../views/tickets/TicketKanban.vue'),
      },
      {
        path: 'tickets/:id',
        name: 'TicketDetail',
        component: () => import('../views/tickets/TicketDetail.vue'),
      },
      {
        path: 'notifications',
        name: 'Notifications',
        component: () => import('../views/notifications/NotificationList.vue'),
      },
      {
        path: 'notification-templates',
        name: 'NotificationTemplates',
        component: () => import('../views/notifications/NotificationTemplateList.vue'),
      },
      {
        path: 'reports',
        name: 'Reports',
        component: () => import('../views/reports/ReportBuilder.vue'),
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
