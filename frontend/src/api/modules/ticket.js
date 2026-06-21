import request from '../request'

export const ticketApi = {
  getTickets(params) {
    return request({
      url: '/tickets',
      method: 'get',
      params,
    })
  },

  getTicketsKanban() {
    return request({
      url: '/tickets-kanban',
      method: 'get',
    })
  },

  getTicket(id) {
    return request({
      url: `/tickets/${id}`,
      method: 'get',
    })
  },

  createTicket(data) {
    return request({
      url: '/tickets',
      method: 'post',
      data,
    })
  },

  updateTicket(id, data) {
    return request({
      url: `/tickets/${id}`,
      method: 'put',
      data,
    })
  },

  updateTicketStatus(id, status, comment) {
    return request({
      url: `/tickets/${id}/status`,
      method: 'put',
      data: { status, comment },
    })
  },

  assignTicket(id, assignedTo) {
    return request({
      url: `/tickets/${id}/assign`,
      method: 'put',
      data: { assigned_to: assignedTo },
    })
  },

  addComment(id, content, isInternal = false) {
    return request({
      url: `/tickets/${id}/comments`,
      method: 'post',
      data: { content, is_internal: isInternal },
    })
  },

  deleteTicket(id) {
    return request({
      url: `/tickets/${id}`,
      method: 'delete',
    })
  },

  getAssignees() {
    return request({
      url: '/tickets-assignees',
      method: 'get',
    })
  },

  getCounts() {
    return request({
      url: '/tickets-counts',
      method: 'get',
    })
  },
}
