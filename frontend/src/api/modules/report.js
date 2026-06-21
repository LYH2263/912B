import request from '../request'

export const reportApi = {
  getOptions() {
    return request({
      url: '/reports/options',
      method: 'get',
    })
  },

  getTemplates() {
    return request({
      url: '/reports/templates',
      method: 'get',
    })
  },

  generateReport(data) {
    return request({
      url: '/reports/generate',
      method: 'post',
      data,
    })
  },

  exportCsv(data) {
    return request({
      url: '/reports/export-csv',
      method: 'post',
      data,
      responseType: 'blob',
    })
  },
}
