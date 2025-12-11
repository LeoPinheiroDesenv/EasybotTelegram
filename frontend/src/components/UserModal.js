import React, { useState, useEffect, useContext, useCallback } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import userService from '../services/userService';
import userGroupService from '../services/userGroupService';
import api from '../services/api';
import './UserModal.css';

const UserModal = ({ user, onClose, onSave }) => {
  const { user: currentUser } = useContext(AuthContext);
  const isSuperAdmin = currentUser?.user_type === 'super_admin';
  const isAdmin = currentUser?.user_type === 'admin' || isSuperAdmin;
  
  // Verifica se o usuário foi criado pelo admin comum atual
  // Super admin pode editar qualquer usuário
  // Admin comum pode editar apenas usuários que ele criou
  // Se não há usuário (criando novo), admin comum pode criar
  const userCreatedByCurrentUser = user && currentUser && (
    user.created_by === currentUser.id || 
    (user.creator && user.creator.id === currentUser.id)
  );
  const canEditUserType = isSuperAdmin || (isAdmin && (!user || userCreatedByCurrentUser));
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    user_type: 'user',
    user_group_id: '',
    role: 'user',
    active: true,
    phone: '',
    description: '',
    address_street: '',
    address_number: '',
    address_zipcode: '',
    state_id: '',
    municipality_id: ''
  });
  const [userGroups, setUserGroups] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [states, setStates] = useState([]);
  const [municipalities, setMunicipalities] = useState([]);
  const [loadingStates, setLoadingStates] = useState(false);
  const [loadingMunicipalities, setLoadingMunicipalities] = useState(false);

  const loadUserGroups = useCallback(async () => {
    try {
      const groups = await userGroupService.getAllGroups();
      setUserGroups(groups);
    } catch (err) {
      console.error('Erro ao carregar grupos:', err);
    }
  }, []);

  const loadStates = useCallback(async () => {
    try {
      setLoadingStates(true);
      const response = await api.get('/profile/states');
      setStates(response.data.states || []);
    } catch (err) {
      console.error('Erro ao carregar estados:', err);
    } finally {
      setLoadingStates(false);
    }
  }, []);

  const loadMunicipalities = useCallback(async (stateId) => {
    try {
      setLoadingMunicipalities(true);
      if (!stateId) {
        setMunicipalities([]);
        return;
      }

      const response = await api.get('/profile/municipalities', {
        params: { state_id: stateId }
      });
      setMunicipalities(response.data.municipalities || []);
    } catch (err) {
      console.error('Erro ao carregar municípios:', err);
      setMunicipalities([]);
    } finally {
      setLoadingMunicipalities(false);
    }
  }, []);

  useEffect(() => {
    loadUserGroups();
    loadStates();
  }, [loadUserGroups, loadStates]);

  useEffect(() => {
    if (formData.state_id) {
      loadMunicipalities(formData.state_id);
    } else {
      setMunicipalities([]);
    }
  }, [formData.state_id, loadMunicipalities]);

  useEffect(() => {
    if (user) {
      setFormData({
        name: user.name || '',
        email: user.email || '',
        password: '',
        user_type: user.user_type || 'user',
        user_group_id: user.user_group_id || '',
        role: user.role || 'user',
        active: user.active !== undefined ? user.active : true,
        phone: user.phone || '',
        description: user.description || '',
        address_street: user.address_street || '',
        address_number: user.address_number || '',
        address_zipcode: user.address_zipcode || '',
        state_id: user.state_id ? String(user.state_id) : '',
        municipality_id: user.municipality_id ? String(user.municipality_id) : ''
      });
      
      // Se houver state_id, carrega os municípios
      if (user.state_id) {
        setTimeout(() => {
          loadMunicipalities(user.state_id);
        }, 100);
      }
    } else {
      setFormData({
        name: '',
        email: '',
        password: '',
        user_type: 'user',
        user_group_id: '',
        role: 'user',
        active: true,
        phone: '',
        description: '',
        address_street: '',
        address_number: '',
        address_zipcode: '',
        state_id: '',
        municipality_id: ''
      });
    }
  }, [user, loadMunicipalities]);

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
              disabled={user && !canEditUserType} // Desabilita apenas se não puder editar (não é super admin e não criou o usuário)
            >
              <option value="user">Usuário (Somente Leitura)</option>
              {isAdmin && (
                <option value="admin">Administrador (Leitura e Edição)</option>
              )}
            </select>
            {!isSuperAdmin && !user && (
              <small>Você pode criar usuários comuns e administradores comuns. Apenas super administradores podem criar super administradores.</small>
            )}
            {!isSuperAdmin && user && !canEditUserType && (
              <small style={{ color: '#dc2626' }}>Você só pode alterar o tipo de usuários que você cadastrou.</small>
            )}
            {isSuperAdmin && !user && (
              <small>Super administradores podem criar qualquer tipo de usuário.</small>
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

          <div className="form-group">
            <label htmlFor="phone">Telefone</label>
            <input
              type="text"
              id="phone"
              name="phone"
              value={formData.phone}
              onChange={handleChange}
              placeholder="(00) 00000-0000"
            />
          </div>

          <div className="form-group">
            <label htmlFor="description">Descrição</label>
            <textarea
              id="description"
              name="description"
              value={formData.description}
              onChange={handleChange}
              rows="3"
              placeholder="Descrição sobre o usuário..."
            />
          </div>

          <div className="form-section-divider">
            <h3>Endereço</h3>
          </div>

          <div className="form-group">
            <label htmlFor="address_street">Rua</label>
            <input
              type="text"
              id="address_street"
              name="address_street"
              value={formData.address_street}
              onChange={handleChange}
              placeholder="Nome da rua"
            />
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="address_number">Número</label>
              <input
                type="text"
                id="address_number"
                name="address_number"
                value={formData.address_number}
                onChange={handleChange}
                placeholder="123"
              />
            </div>

            <div className="form-group">
              <label htmlFor="address_zipcode">CEP</label>
              <input
                type="text"
                id="address_zipcode"
                name="address_zipcode"
                value={formData.address_zipcode}
                onChange={handleChange}
                placeholder="00000-000"
                maxLength="9"
              />
            </div>
          </div>

          <div className="form-group">
            <label htmlFor="state_id">Estado</label>
            <select
              id="state_id"
              name="state_id"
              value={formData.state_id}
              onChange={handleChange}
              disabled={loadingStates}
            >
              <option value="">Selecione um estado</option>
              {states.map(state => (
                <option key={state.id} value={state.id}>
                  {state.nome} ({state.uf})
                </option>
              ))}
            </select>
          </div>

          <div className="form-group">
            <label htmlFor="municipality_id">Cidade</label>
            <select
              id="municipality_id"
              name="municipality_id"
              value={formData.municipality_id}
              onChange={handleChange}
              disabled={!formData.state_id || loadingMunicipalities}
            >
              <option value="">Selecione uma cidade</option>
              {municipalities.map(municipality => (
                <option key={municipality.id} value={municipality.id}>
                  {municipality.nome}
                </option>
              ))}
            </select>
            {!formData.state_id && (
              <small>Selecione um estado primeiro</small>
            )}
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

