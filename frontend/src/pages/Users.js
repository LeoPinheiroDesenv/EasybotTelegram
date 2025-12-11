import React, { useState, useEffect, useContext } from 'react';
import { AuthContext } from '../contexts/AuthContext';
import Layout from '../components/Layout';
import userService from '../services/userService';
import UserModal from '../components/UserModal';
import useConfirm from '../hooks/useConfirm';
import RefreshButton from '../components/RefreshButton';
import './Users.css';

const Users = () => {
  const { confirm, DialogComponent } = useConfirm();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const { user: currentUser, isAdmin, isSuperAdmin } = useContext(AuthContext);

  useEffect(() => {
    loadUsers();
  }, []);

  const loadUsers = async () => {
    try {
      setLoading(true);
      const data = await userService.getAllUsers();
      setUsers(data);
      setError('');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao carregar usuários');
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = () => {
    setEditingUser(null);
    setIsModalOpen(true);
  };

  const handleEdit = (user) => {
    setEditingUser(user);
    setIsModalOpen(true);
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja excluir este usuário?',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    try {
      await userService.deleteUser(id);
      setSuccess('Usuário excluído com sucesso!');
      loadUsers();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao excluir usuário');
      setTimeout(() => setError(''), 3000);
    }
  };

  const handleModalClose = () => {
    setIsModalOpen(false);
    setEditingUser(null);
  };

  const handleModalSave = () => {
    loadUsers();
    handleModalClose();
    setSuccess(editingUser ? 'Usuário atualizado com sucesso!' : 'Usuário criado com sucesso!');
    setTimeout(() => setSuccess(''), 3000);
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  if (!isAdmin) {
  return (
    <Layout>
      <DialogComponent />
      <div className="container">
          <div className="card">
            <h1>Acesso Negado</h1>
            <p>Você não tem permissão para acessar esta página.</p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="users-page">
        <div className="users-container">
        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        <div className="users-toolbar">
          <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
            <RefreshButton onRefresh={loadUsers} loading={loading} />
            <button onClick={handleCreate} className="btn btn-primary">
              + Novo Usuário
            </button>
          </div>
        </div>

        {loading ? (
          <div className="loading">Carregando usuários...</div>
        ) : (
          <div className="users-table-container">
            <table className="users-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nome</th>
                  <th>Email</th>
                  <th>Nível de Acesso</th>
                  <th>Status</th>
                  {isSuperAdmin && <th>Criado por</th>}
                  <th>Criado em</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {users.length === 0 ? (
                  <tr>
                    <td colSpan={isSuperAdmin ? 8 : 7} className="text-center">
                      Nenhum usuário encontrado
                    </td>
                  </tr>
                ) : (
                  users.map((user) => (
                    <tr key={user.id}>
                      <td>{user.id}</td>
                      <td>{user.name}</td>
                      <td>{user.email}</td>
                      <td>
                        <span className={`badge badge-${user.user_type === 'super_admin' ? 'super-admin' : user.role}`}>
                          {user.user_type === 'super_admin' 
                            ? 'Super Administrador' 
                            : user.user_type === 'admin' 
                            ? 'Administrador' 
                            : 'Usuário'}
                        </span>
                      </td>
                      <td>
                        <span className={`badge badge-${user.active ? 'active' : 'inactive'}`}>
                          {user.active ? 'Ativo' : 'Inativo'}
                        </span>
                      </td>
                      {isSuperAdmin && (
                        <td>
                          {user.creator ? (
                            <span title={`ID: ${user.creator.id}`}>
                              {user.creator.name} ({user.creator.email})
                            </span>
                          ) : (
                            <span style={{ color: '#9ca3af', fontStyle: 'italic' }}>Sistema</span>
                          )}
                        </td>
                      )}
                      <td>{formatDate(user.created_at)}</td>
                      <td>
                        <div className="actions">
                          <button
                            onClick={() => handleEdit(user)}
                            className="btn btn-sm btn-secondary"
                          >
                            Editar
                          </button>
                          <button
                            onClick={() => handleDelete(user.id)}
                            className="btn btn-sm btn-danger"
                            disabled={user.id === currentUser?.id}
                          >
                            Excluir
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
        </div>

        {isModalOpen && (
          <UserModal
            user={editingUser}
            onClose={handleModalClose}
            onSave={handleModalSave}
          />
        )}
      </div>
    </Layout>
  );
};

export default Users;

