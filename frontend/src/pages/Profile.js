import React, { useState, useEffect, useContext, useRef } from 'react';
import Layout from '../components/Layout';
import { AuthContext } from '../contexts/AuthContext';
import api from '../services/api';
import './Profile.css';

const Profile = () => {
  const { user: currentUser, refreshUser, isSuperAdmin } = useContext(AuthContext);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [consultingCep, setConsultingCep] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [activeTab, setActiveTab] = useState('edit');
  const fileInputRef = useRef(null);
  
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    description: '',
    address_street: '',
    address_number: '',
    address_zipcode: '',
    state_id: '',
    municipality_id: '',
    password: '',
    password_confirmation: '',
  });

  const [avatarUrl, setAvatarUrl] = useState(null);
  const [states, setStates] = useState([]);
  const [municipalities, setMunicipalities] = useState([]);
  const [loadingStates, setLoadingStates] = useState(false);
  const [loadingMunicipalities, setLoadingMunicipalities] = useState(false);

  useEffect(() => {
    loadProfile();
    loadStates();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (formData.state_id) {
      loadMunicipalities(formData.state_id);
    } else {
      setMunicipalities([]);
    }
  }, [formData.state_id]);

  const loadProfile = async () => {
    try {
      setLoading(true);
      const response = await api.get('/profile');
      const user = response.data.user;
      
      setFormData({
        name: user.name || '',
        email: user.email || '',
        phone: user.phone || '',
        description: user.description || '',
        address_street: user.address_street || '',
        address_number: user.address_number || '',
        address_zipcode: user.address_zipcode || '',
        state_id: user.state_id ? String(user.state_id) : '',
        municipality_id: user.municipality_id ? String(user.municipality_id) : '',
        password: '',
        password_confirmation: '',
      });

      setAvatarUrl(user.avatar || null);
      
      // Se houver state_id, carrega os municípios
      if (user.state_id) {
        setTimeout(() => {
          loadMunicipalities(user.state_id);
        }, 100);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar perfil');
    } finally {
      setLoading(false);
    }
  };

  const loadStates = async () => {
    try {
      setLoadingStates(true);
      const response = await api.get('/profile/states');
      setStates(response.data.states || []);
    } catch (err) {
      console.error('Erro ao carregar estados:', err);
    } finally {
      setLoadingStates(false);
    }
  };

  const loadMunicipalities = async (stateId) => {
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
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    setError('');
  };

  const handleAvatarClick = () => {
    fileInputRef.current?.click();
  };

  const handleAvatarChange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Validação de tipo e tamanho
    if (!file.type.startsWith('image/')) {
      setError('Por favor, selecione uma imagem válida');
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      setError('A imagem deve ter no máximo 2MB');
      return;
    }

    try {
      setUploadingAvatar(true);
      setError('');
      
      const formData = new FormData();
      formData.append('avatar', file);

      const response = await api.post('/profile/avatar', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });

      if (response.data.success) {
        setAvatarUrl(response.data.url);
        setSuccess('Foto de perfil atualizada com sucesso!');
        await refreshUser();
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors?.avatar?.[0] || 'Erro ao fazer upload da foto');
    } finally {
      setUploadingAvatar(false);
      // Limpa o input para permitir selecionar o mesmo arquivo novamente
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleConsultCep = async () => {
    const cep = formData.address_zipcode.replace(/\D/g, '');
    
    if (cep.length !== 8) {
      setError('CEP deve conter 8 dígitos');
      return;
    }

    try {
      setConsultingCep(true);
      setError('');
      
      const response = await api.get('/profile/consult-cep', {
        params: { cep }
      });

      const data = response.data;
      
      setFormData(prev => ({
        ...prev,
        address_street: data.logradouro || prev.address_street,
        state_id: data.state_id ? String(data.state_id) : prev.state_id,
        municipality_id: data.municipality_id ? String(data.municipality_id) : prev.municipality_id,
      }));

      if (data.state_id) {
        setTimeout(() => {
          loadMunicipalities(data.state_id).then(() => {
            if (data.municipality_id) {
              setFormData(prev => ({
                ...prev,
                municipality_id: String(data.municipality_id)
              }));
            }
          });
        }, 200);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao consultar CEP');
    } finally {
      setConsultingCep(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    // Validação de senha
    if (formData.password && formData.password !== formData.password_confirmation) {
      setError('As senhas não coincidem');
      return;
    }

    if (formData.password && formData.password.length < 6) {
      setError('A senha deve ter no mínimo 6 caracteres');
      return;
    }

    try {
      setLoading(true);
      
      const dataToSend = { ...formData };
      
      // Remove campos vazios de senha
      if (!dataToSend.password) {
        delete dataToSend.password;
        delete dataToSend.password_confirmation;
      } else {
        delete dataToSend.password_confirmation;
      }

      // Remove email se não for super admin
      if (!isSuperAdmin) {
        delete dataToSend.email;
      }

      await api.put('/profile', dataToSend);
      
      setSuccess('Perfil atualizado com sucesso!');
      await refreshUser();
      
      // Limpa campos de senha
      setFormData(prev => ({
        ...prev,
        password: '',
        password_confirmation: ''
      }));

      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.errors || 'Erro ao atualizar perfil');
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    loadProfile();
    setError('');
    setSuccess('');
  };

  const formatCep = (value) => {
    const cep = value.replace(/\D/g, '');
    if (cep.length <= 8) {
      return cep.replace(/(\d{5})(\d{3})/, '$1-$2');
    }
    return value;
  };

  const formatPhone = (value) => {
    const phone = value.replace(/\D/g, '');
    if (phone.length <= 11) {
      return phone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
        .replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')
        .replace(/(\d{2})(\d{4})/, '($1) $2');
    }
    return value;
  };

  // Função para obter nome do estado
  const getStateName = (stateId) => {
    const state = states.find(s => s.id === parseInt(stateId));
    return state ? `${state.nome} (${state.uf})` : '';
  };

  // Função para obter nome do município
  const getMunicipalityName = (municipalityId) => {
    const municipality = municipalities.find(m => m.id === parseInt(municipalityId));
    return municipality ? municipality.nome : '';
  };

  return (
    <Layout>
      <div className="profile-page">
        <div className="profile-container">
          
          {error && <div className="alert alert-error">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          <div className="profile-layout">
            {/* Left Column - Profile Summary */}
            <div className="profile-summary">
              <div className="profile-banner">
                {/* Banner placeholder - pode ser expandido no futuro */}
              </div>
              <div className="profile-summary-content">
                <div className="profile-summary-avatar">
                  {avatarUrl ? (
                    <img src={avatarUrl} alt="Avatar" />
                  ) : (
                    <div className="profile-summary-avatar-placeholder">
                      <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                      </svg>
                    </div>
                  )}
                </div>
                <h2 className="profile-summary-name">{formData.name || currentUser?.name || 'Nome do Usuário'}</h2>
                <p className="profile-summary-email">{formData.email || currentUser?.email || 'email@exemplo.com'}</p>
                
                <div className="personal-info-section">
                  <h3>Informações Pessoais</h3>
                  <div className="personal-info-list">
                    <div className="personal-info-item">
                      <span className="info-label">Nome Completo:</span>
                      <span className="info-value">{formData.name || '-'}</span>
                    </div>
                    <div className="personal-info-item">
                      <span className="info-label">Email:</span>
                      <span className="info-value">{formData.email || '-'}</span>
                    </div>
                    <div className="personal-info-item">
                      <span className="info-label">Telefone:</span>
                      <span className="info-value">{formData.phone || '-'}</span>
                    </div>
                    {formData.address_street && (
                      <div className="personal-info-item">
                        <span className="info-label">Endereço:</span>
                        <span className="info-value">
                          {formData.address_street}
                          {formData.address_number && `, ${formData.address_number}`}
                          {formData.address_zipcode && ` - ${formData.address_zipcode}`}
                        </span>
                      </div>
                    )}
                    {formData.state_id && (
                      <div className="personal-info-item">
                        <span className="info-label">Estado:</span>
                        <span className="info-value">{getStateName(formData.state_id) || '-'}</span>
                      </div>
                    )}
                    {formData.municipality_id && (
                      <div className="personal-info-item">
                        <span className="info-label">Cidade:</span>
                        <span className="info-value">{getMunicipalityName(formData.municipality_id) || '-'}</span>
                      </div>
                    )}
                    {formData.description && (
                      <div className="personal-info-item">
                        <span className="info-label">Biografia:</span>
                        <span className="info-value">{formData.description}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Right Column - Edit Form */}
            <div className="profile-edit">
              {/* Tabs */}
              <div className="profile-tabs">
                <button
                  className={`profile-tab ${activeTab === 'edit' ? 'active' : ''}`}
                  onClick={() => setActiveTab('edit')}
                >
                  Editar Perfil
                </button>
                <button
                  className={`profile-tab ${activeTab === 'address' ? 'active' : ''}`}
                  onClick={() => setActiveTab('address')}
                >
                  Endereço
                </button>
                <button
                  className={`profile-tab ${activeTab === 'password' ? 'active' : ''}`}
                  onClick={() => setActiveTab('password')}
                >
                  Alterar Senha
                </button>
              </div>

              {/* Tab Content */}
              {activeTab === 'edit' && (
                <form onSubmit={handleSubmit} className="profile-form">
                  {/* Profile Image Section */}
                  <div className="profile-image-section">
                    <h3>Foto de Perfil</h3>
                    <div className="profile-image-container">
                      <div className="profile-image-wrapper" onClick={handleAvatarClick}>
                        {avatarUrl ? (
                          <img src={avatarUrl} alt="Avatar" className="profile-image" />
                        ) : (
                          <div className="profile-image-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                              <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                          </div>
                        )}
                        <div className="profile-image-overlay">
                          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                          </svg>
                        </div>
                      </div>
                      <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        onChange={handleAvatarChange}
                        style={{ display: 'none' }}
                      />
                      {uploadingAvatar && <p className="upload-status">Enviando...</p>}
                    </div>
                  </div>

                  {/* Form Fields */}
                  <div className="form-fields">
                    <div className="form-group">
                      <label htmlFor="name">Nome Completo *</label>
                      <input
                        type="text"
                        id="name"
                        name="name"
                        value={formData.name}
                        onChange={handleChange}
                        required
                        placeholder="Digite seu nome completo"
                      />
                    </div>

                    <div className="form-group">
                      <label htmlFor="email">Email {isSuperAdmin ? '*' : '(apenas super administrador pode alterar)'}</label>
                      <input
                        type="email"
                        id="email"
                        name="email"
                        value={formData.email}
                        onChange={handleChange}
                        required={isSuperAdmin}
                        disabled={!isSuperAdmin}
                        placeholder="Digite seu email"
                      />
                      {!isSuperAdmin && (
                        <small className="form-help">Apenas super administradores podem alterar o email</small>
                      )}
                    </div>

                    <div className="form-group">
                      <label htmlFor="phone">Telefone</label>
                      <input
                        type="text"
                        id="phone"
                        name="phone"
                        value={formData.phone}
                        onChange={(e) => {
                          const formatted = formatPhone(e.target.value);
                          setFormData(prev => ({ ...prev, phone: formatted }));
                        }}
                        placeholder="Digite seu telefone"
                        maxLength={15}
                      />
                    </div>

                    <div className="form-group">
                      <label htmlFor="description">Descrição</label>
                      <textarea
                        id="description"
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                        placeholder="Escreva uma descrição sobre você..."
                        rows={4}
                        maxLength={1000}
                      />
                      <small className="form-help">{formData.description.length}/1000 caracteres</small>
                    </div>
                  </div>

                  {/* Form Actions */}
                  <div className="form-actions">
                    <button
                      type="button"
                      onClick={handleCancel}
                      className="btn btn-cancel"
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
              )}

              {activeTab === 'address' && (
                <form onSubmit={handleSubmit} className="profile-form">
                  <div className="form-fields">
                    <h3>Endereço</h3>
                    
                    <div className="form-row">
                      <div className="form-group" style={{ width: '100%' }}>
                        <label htmlFor="address_zipcode">CEP</label>
                        <div className="cep-input-group">
                          <input
                            type="text"
                            id="address_zipcode"
                            name="address_zipcode"
                            value={formatCep(formData.address_zipcode)}
                            onChange={(e) => {
                              const formatted = formatCep(e.target.value);
                              setFormData(prev => ({ ...prev, address_zipcode: formatted }));
                            }}
                            placeholder="00000-000"
                            maxLength={9}
                            style={{ width: '250px' }}
                          />
                          <button
                            type="button"
                            onClick={handleConsultCep}
                            disabled={consultingCep || formData.address_zipcode.replace(/\D/g, '').length !== 8}
                            className="btn btn-secondary btn-consult-cep"
                          >
                            {consultingCep ? 'Consultando...' : 'Consultar CEP'}
                          </button>
                        </div>
                      </div>
                    </div>

                    <div className="form-row">
                      <div className="form-group form-group-flex-2">
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
                      <div className="form-group form-group-flex-1">
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
                    </div>

                    <div className="form-row">
                      <div className="form-group form-group-flex-1">
                        <label htmlFor="state_id">Estado *</label>
                        <select
                          id="state_id"
                          name="state_id"
                          value={formData.state_id}
                          onChange={handleChange}
                          required
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
                      <div className="form-group form-group-flex-2">
                        <label htmlFor="municipality_id">Cidade *</label>
                        <select
                          id="municipality_id"
                          name="municipality_id"
                          value={formData.municipality_id}
                          onChange={handleChange}
                          required
                          disabled={!formData.state_id || loadingMunicipalities}
                        >
                          <option value="">
                            {!formData.state_id 
                              ? 'Selecione primeiro um estado' 
                              : loadingMunicipalities 
                              ? 'Carregando...' 
                              : 'Selecione uma cidade'}
                          </option>
                          {municipalities.map(municipality => (
                            <option key={municipality.id} value={municipality.id}>
                              {municipality.nome}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>

                  {/* Form Actions */}
                  <div className="form-actions">
                    <button
                      type="button"
                      onClick={handleCancel}
                      className="btn btn-cancel"
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
              )}

              {activeTab === 'password' && (
                <form onSubmit={handleSubmit} className="profile-form">
                  <div className="form-fields">
                    <h3>Alterar Senha</h3>
                    <p className="form-help">Deixe em branco se não desejar alterar a senha</p>
                    
                    <div className="form-row">
                      <div className="form-group form-group-flex-1">
                        <label htmlFor="password">Nova Senha</label>
                        <input
                          type="password"
                          id="password"
                          name="password"
                          value={formData.password}
                          onChange={handleChange}
                          placeholder="Mínimo 6 caracteres"
                          minLength={6}
                        />
                      </div>
                      <div className="form-group form-group-flex-1">
                        <label htmlFor="password_confirmation">Confirmar Nova Senha</label>
                        <input
                          type="password"
                          id="password_confirmation"
                          name="password_confirmation"
                          value={formData.password_confirmation}
                          onChange={handleChange}
                          placeholder="Digite a senha novamente"
                          minLength={6}
                        />
                      </div>
                    </div>
                  </div>

                  <div className="form-actions">
                    <button
                      type="button"
                      onClick={handleCancel}
                      className="btn btn-cancel"
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
              )}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Profile;
