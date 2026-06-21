import request from '../request'

export const bundleApi = {
  getBundles(params) {
    return request({
      url: '/bundles',
      method: 'get',
      params,
    })
  },

  getBundle(id) {
    return request({
      url: `/bundles/${id}`,
      method: 'get',
    })
  },

  createBundle(data) {
    return request({
      url: '/bundles',
      method: 'post',
      data,
    })
  },

  updateBundle(id, data) {
    return request({
      url: `/bundles/${id}`,
      method: 'put',
      data,
    })
  },

  deleteBundle(id) {
    return request({
      url: `/bundles/${id}`,
      method: 'delete',
    })
  },

  toggleBundle(id) {
    return request({
      url: `/bundles/${id}/toggle`,
      method: 'put',
    })
  },
}
