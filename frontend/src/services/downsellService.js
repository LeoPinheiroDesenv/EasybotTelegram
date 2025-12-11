import api from './api';

const downsellService = {
  async getDownsells(botId) {
    const response = await api.get('/downsells', {
      params: { botId }
    });
    return response.data.downsells;
  },

  async getDownsell(id) {
    const response = await api.get(`/downsells/${id}`);
    return response.data.downsell;
  },

  async createDownsell(formData) {
    const data = new FormData();
    
    Object.keys(formData).forEach(key => {
      if (formData[key] !== null && formData[key] !== undefined && formData[key] !== '') {
        if (key === 'initial_media' && formData[key] instanceof File) {
          data.append('initial_media', formData[key]);
        } else if (key !== 'initial_media') {
          data.append(key, formData[key]);
        }
      }
    });

    const response = await api.post('/downsells', data, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    return response.data.downsell;
  },

  async updateDownsell(id, formData) {
    const data = new FormData();
    
    Object.keys(formData).forEach(key => {
      if (formData[key] !== null && formData[key] !== undefined && formData[key] !== '') {
        if (key === 'initial_media' && formData[key] instanceof File) {
          data.append('initial_media', formData[key]);
        } else if (key !== 'initial_media') {
          data.append(key, formData[key]);
        }
      }
    });

    const response = await api.put(`/downsells/${id}`, data, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    return response.data.downsell;
  },

  async deleteDownsell(id) {
    const response = await api.delete(`/downsells/${id}`);
    return response.data;
  }
};

export default downsellService;
