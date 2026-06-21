import request from '../request'

export const orderApi = {
  // 获取订单列表
  getOrders(params) {
    return request({
      url: '/orders',
      method: 'get',
      params,
    })
  },

  // 获取订单详情
  getOrder(id) {
    return request({
      url: `/orders/${id}`,
      method: 'get',
    })
  },

  // 创建订单
  createOrder(data) {
    return request({
      url: '/orders',
      method: 'post',
      data,
    })
  },

  // 更新订单状态
  updateOrderStatus(id, status) {
    return request({
      url: `/orders/${id}/status`,
      method: 'put',
      data: { status },
    })
  },

  splitOrder(id, splitItemIds) {
    return request({
      url: `/orders/${id}/split`,
      method: 'post',
      data: { split_item_ids: splitItemIds },
    })
  },

  mergeOrders(orderId1, orderId2) {
    return request({
      url: '/orders/merge',
      method: 'post',
      data: { order_id_1: orderId1, order_id_2: orderId2 },
    })
  },

  getMergeCandidates(orderId) {
    return request({
      url: '/orders/merge-candidates',
      method: 'get',
      params: { order_id: orderId },
    })
  },
}
