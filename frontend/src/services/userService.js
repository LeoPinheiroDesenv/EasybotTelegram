import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost:5000/api';

const api = axios.create({
  baseURL: API_URL,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

const userService = {
  getAllUsers: async () => {
    const response = await api.get('/users');
    return response.data.users;
  },

  getUserById: async (id) => {
    const response = await api.get(`/users/${id}`);
    return response.data.user;
  },

  createUser: async (userData) => {
    const response = await api.post('/users', userData);
    return response.data.user;
  },

  updateUser: async (id, userData) => {
    const response = await api.put(`/users/${id}`, userData);
    return response.data.user;
  },

  deleteUser: async (id) => {
    const response = await api.delete(`/users/${id}`);
    return response.data;
  }
};

export default userService;

