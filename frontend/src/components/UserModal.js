import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import userService from '../services/userService';
import userGroupService from '../services/userGroupService';
import './UserModal.css';

const UserModal = ({ user, onClose, onSave }) => {
  const { user: currentUser } = useContext(AuthContext);
  const isSuperAdmin = currentUser?.user_type === 'super_admin';
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    user_type: 'user',
    user_group_id: '',
    role: 'user',
    active: true
  });
  const [userGroups, setUserGroups] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadUserGroups();
  }, []);

  useEffect(() => {
    if (user) {
      setFormData({
        name: user.name || '',
        email: user.email || '',
        password: '',
        user_type: user.user_type || 'user',
        user_group_id: user.user_group_id || '',
        role: user.role || 'user',
        active: user.active !== undefined ? user.active : true
      });
    } else {
      setFormData({
        name: '',
        email: '',
        password: '',
        user_type: 'user',
        user_group_id: '',
        role: 'user',
        active: true
      });
    }
  }, [user]);

  const loadUserGroups = async () => {
    try {
      const groups = await userGroupService.getAllGroups();
      setUserGroups(groups);
    } catch (err) {
      console.error('Erro ao carregar grupos:', err);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    const newFormData = {
      ...formData,
      [name]: type === 'checkbox' ? checked : value
    };
    
    // Sincronizar role com user_type
    if (name === 'user_type') {
      newFormData.role = value === 'admin' ? 'admin' : 'user';
    }
    
    setFormData(newFormData);
    setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const data = { ...formData };
      
      // Remove password if editing and password is empty
      if (user && !data.password) {
        delete data.password;
      }

      if (user) {
        await userService.updateUser(user.id, data);
      } else {
        if (!data.password) {
          setError('Senha é obrigatória para novos usuários');
          setLoading(false);
          return;
        }
        await userService.createUser(data);
      }

      onSave();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao salvar usuário');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{user ? 'Editar Usuário' : 'Novo Usuário'}</h2>
          <button className="modal-close" onClick={onClose}>×</button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="name">Nome *</label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              required
              placeholder="Nome completo"
            />
          </div>

          <div className="form-group">
            <label htmlFor="email">Email *</label>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              required
              placeholder="email@exemplo.com"
            />
          </div>

          <div className="form-group">
            <label htmlFor="password">
              Senha {user ? '(deixe em branco para não alterar)' : '*'}
            </label>
            <input
              type="password"
              id="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              required={!user}
              minLength={6}
              placeholder="Mínimo 6 caracteres"
            />
          </div>

          <div className="form-group">
            <label htmlFor="user_type">Tipo de Usuário *</label>
            <select
              id="user_type"
              name="user_type"
              value={formData.user_type}
              onChange={handleChange}
              required
              disabled={user && !isSuperAdmin} // Desabilita edição se não for super admin
            >
              <option value="user">Usuário (Somente Leitura)</option>
              {isSuperAdmin && (
                <option value="admin">Administrador (Leitura e Edição)</option>
              )}
            </select>
            {!isSuperAdmin && !user && (
              <small>Você só pode criar usuários. Apenas super administradores podem criar administradores.</small>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="user_group_id">Grupo de Usuários</label>
            <select
              id="user_group_id"
              name="user_group_id"
              value={formData.user_group_id}
              onChange={handleChange}
            >
              <option value="">Selecione um grupo</option>
              {userGroups.map(group => (
                <option key={group.id} value={group.id}>
                  {group.name}
                </option>
              ))}
            </select>
            <small>O grupo define quais menus e bots o usuário pode acessar</small>
          </div>

          <div className="form-group checkbox-group">
            <label>
              <input
                type="checkbox"
                name="active"
                checked={formData.active}
                onChange={handleChange}
              />
              Usuário Ativo
            </label>
          </div>

          {error && <div className="error">{error}</div>}

          <div className="modal-actions">
            <button
              type="button"
              onClick={onClose}
              className="btn btn-secondary"
              disabled={loading}
            >
              Cancelar
            </button>
            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading}
            >
              {loading ? 'Salvando...' : 'Salvar'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default UserModal;

