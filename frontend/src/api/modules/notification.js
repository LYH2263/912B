import request from '../request'

export const notificationApi = {
  getNotifications(params) {
    return request({
      url: '/notifications',
      method: 'get',
      params,
    })
  },

  getUnreadCount() {
    return request({
      url: '/notifications/unread-count',
      method: 'get',
    })
  },

  getNotification(id) {
    return request({
      url: `/notifications/${id}`,
      method: 'get',
    })
  },

  markAsRead(id) {
    return request({
      url: `/notifications/${id}/read`,
      method: 'put',
    })
  },

  markAllAsRead() {
    return request({
      url: '/notifications/mark-all-read',
      method: 'post',
    })
  },

  getTemplates(params) {
    return request({
      url: '/notification-templates',
      method: 'get',
      params,
    })
  },

  getAllTemplates() {
    return request({
      url: '/notification-templates/all',
      method: 'get',
    })
  },

  getTemplate(id) {
    return request({
      url: `/notification-templates/${id}`,
      method: 'get',
    })
  },

  createTemplate(data) {
    return request({
      url: '/notification-templates',
      method: 'post',
      data,
    })
  },

  updateTemplate(id, data) {
    return request({
      url: `/notification-templates/${id}`,
      method: 'put',
      data,
    })
  },

  deleteTemplate(id) {
    return request({
      url: `/notification-templates/${id}`,
      method: 'delete',
    })
  },

  toggleTemplate(id) {
    return request({
      url: `/notification-templates/${id}/toggle`,
      method: 'put',
    })
  },
}
