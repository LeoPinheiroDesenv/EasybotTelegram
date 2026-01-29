import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import contactService from '../services/contactService';
import groupManagementService from '../services/groupManagementService';
import botService from '../services/botService';
import Modal from '../components/Modal';
import { useNavigate } from 'react-router-dom';
import './Contacts.css';

const Contacts = () => {
  const [contacts, setContacts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const [botId, setBotId] = useState(null);
  const [bots, setBots] = useState([]);
  const [memberStatuses, setMemberStatuses] = useState({});
  const [managingMember, setManagingMember] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [sortConfig, setSortConfig] = useState({ key: 'name', direction: 'ascending' });
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(10);
  const [modalState, setModalState] = useState({
    details: { isOpen: false, contact: null },
    verify: { isOpen: false, result: null },
    addGroup: { isOpen: false, contact: null },
    removeGroup: { isOpen: false, contact: null },
    block: { isOpen: false, contact: null },
  });

  const openModal = (modal, data) => {
    setModalState((prev) => ({ ...prev, [modal]: { isOpen: true, ...data } }));
  };

  const closeModal = (modal) => {
    setModalState((prev) => ({ ...prev, [modal]: { isOpen: false, [Object.keys(prev[modal])[1]]: null } }));
  };

  useEffect(() => {
    const fetchBots = async () => {
      try {
        const data = await botService.getAllBots();
        setBots(data);
      } catch (err) {
        console.error('Erro ao carregar bots:', err);
      }
    };

    fetchBots();
  }, []);

  useEffect(() => {
    const fetchContacts = async () => {
      try {
        const selectedBotId = localStorage.getItem('selectedBotId');
        if (!selectedBotId) {
          setError('Nenhum bot selecionado. Por favor, selecione um bot primeiro.');
          setLoading(false);
          return;
        }
        setBotId(selectedBotId);
        const data = await contactService.getAllContacts(selectedBotId);
        setContacts(data);
      } catch (err) {
        setError(err.response?.data?.error || 'Erro ao carregar contatos');
      } finally {
        setLoading(false);
      }
    };

    fetchContacts();
  }, [botId]);

  const checkMemberStatus = async (contactId) => {
    try {
      const status = await groupManagementService.checkMemberStatus(botId, contactId);
      openModal('verify', { result: status });
    } catch (err) {
      console.error('Erro ao verificar status do membro:', err);
    }
  };

  const handleManageMember = async (contact, action) => {
    openModal(action === 'add' ? 'addGroup' : 'removeGroup', { contact });
  };

  const handleBlock = async (contact) => {
    openModal('block', { contact });
  };

  const handleShowDetails = (contact) => {
    openModal('details', { contact });
  };
  
  const confirmAddGroup = async (contact) => {
    setManagingMember(contact.id);
    try {
      await groupManagementService.addMember(botId, contact.id);
      checkMemberStatus(contact.id);
    } catch (err) {
      console.error(`Erro ao adicionar membro:`, err);
    } finally {
      setManagingMember(null);
      closeModal('addGroup');
    }
  };
  
  const confirmRemoveGroup = async (contact) => {
    setManagingMember(contact.id);
    try {
      await groupManagementService.removeMember(botId, contact.id);
      checkMemberStatus(contact.id);
    } catch (err) {
      console.error(`Erro ao remover membro:`, err);
    }
    finally {
      setManagingMember(null);
      closeModal('removeGroup');
    }
  };
  
  const confirmBlock = async (contact) => {
    try {
      await contactService.blockContact(contact.id, botId);
      setContacts((prev) =>
        prev.map((c) =>
          c.id === contact.id ? { ...c, is_blocked: !c.is_blocked } : c
        )
      );
    } catch (err) {
      console.error('Erro ao bloquear/desbloquear contato:', err);
    } finally {
      closeModal('block');
    }
  };

  const handleBotFilterChange = (e) => {
    const selectedBotId = e.target.value;
    localStorage.setItem('selectedBotId', selectedBotId);
    setBotId(selectedBotId);
  };
  
  const filteredContacts = contacts.filter((contact) =>
    (contact.name && contact.name.toLowerCase().includes(searchTerm.toLowerCase())) ||
    (contact.username && contact.username.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  const sortedContacts = React.useMemo(() => {
    let sortableItems = [...filteredContacts];
    if (sortConfig !== null) {
      sortableItems.sort((a, b) => {
        if (a[sortConfig.key] < b[sortConfig.key]) {
          return sortConfig.direction === 'ascending' ? -1 : 1;
        }
        if (a[sortConfig.key] > b[sortConfig.key]) {
          return sortConfig.direction === 'ascending' ? 1 : -1;
        }
        return 0;
      });
    }
    return sortableItems;
  }, [filteredContacts, sortConfig]);

  const requestSort = (key) => {
    let direction = 'ascending';
    if (
      sortConfig &&
      sortConfig.key === key &&
      sortConfig.direction === 'ascending'
    ) {
      direction = 'descending';
    }
    setSortConfig({ key, direction });
  };

  const indexOfLastItem = currentPage * itemsPerPage;
  const indexOfFirstItem = indexOfLastItem - itemsPerPage;
  const currentItems = sortedContacts.slice(indexOfFirstItem, indexOfLastItem);

  const paginate = (pageNumber) => setCurrentPage(pageNumber);

  return (
    <Layout>
      <div className="contacts-page">
        <div className="contacts-header">
          <h1>Contatos</h1>
          <div className="contacts-filters">
            <input
              type="text"
              placeholder="Pesquisar..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="search-input"
            />
            <select onChange={handleBotFilterChange} value={botId || ''}>
              <option value="" disabled>Selecione um bot</option>
              {bots.map((bot) => (
                <option key={bot.id} value={bot.id}>
                  {bot.name}
                </option>
              ))}
            </select>
            <button onClick={() => window.location.reload()} className="btn btn-secondary">
              Sincronizar
            </button>
          </div>
        </div>
        {loading && <p>Carregando...</p>}
        {error && <p className="error">{error}</p>}
        {!loading && !error && (
          <div className="contacts-table-container">
            <table className="contacts-table">
              <thead>
                <tr>
                  <th onClick={() => requestSort('name')}>Nome</th>
                  <th onClick={() => requestSort('username')}>Username</th>
                  <th onClick={() => requestSort('phone')}>Telefone</th>
                  <th onClick={() => requestSort('email')}>Email</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                {currentItems.map((contact) => (
                  <tr key={contact.id}>
                    <td>{contact.name}</td>
                    <td>{contact.username}</td>
                    <td>{contact.phone}</td>
                    <td>{contact.email}</td>
                    <td>
                      <button
                        onClick={() => handleShowDetails(contact)}
                        className="btn btn-info-100 text-primary-600 radius-8 px-14 py-6 text-sm"
                        title="Detalhes"
                      >
                        Detalhes
                      </button>
                      {memberStatuses[contact.id] && (
                        <span className={`member-status-badge ${memberStatuses[contact.id].is_member ? 'member' : 'not-member'}`}>
                          {memberStatuses[contact.id].is_member ? '✓ No grupo' : '✗ Fora'}
                        </span>
                      )}
                      {!contact.is_bot && (
                        <>
                          {managingMember === contact.id ? (
                            <span className="loading-spinner">...</span>
                          ) : (
                            <>
                              <button
                                onClick={() => checkMemberStatus(contact.id)}
                                className="btn btn-primary-100 text-primary-600  radius-8 px-14 py-6 text-sm"
                                title="Verificar status no grupo"
                                disabled={!botId}
                              >
                                Verificar
                              </button>
                              <button
                                onClick={() => handleManageMember(contact, 'add')}
                                className="btn btn-success-100 text-success-600 radius-8 px-14 py-6 text-sm"
                                title="Adicionar ao grupo"
                                disabled={!botId}
                              >
                                + Grupo
                              </button>
                              <button
                                onClick={() => handleManageMember(contact, 'remove')}
                                className="btn btn-warning-100 text-warning-600 radius-8 px-14 py-6 text-sm"
                                title="Remover do grupo"
                                disabled={!botId}
                              >
                                - Grupo
                              </button>
                              <button
                                onClick={() => handleBlock(contact)}
                                className="btn btn-danger-100 text-danger-600 radius-8 px-14 py-6 text-sm"
                                title="Bloquear"
                              >
                                Bloquear
                              </button>
                            </>
                          )}
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="pagination">
              {Array.from({ length: Math.ceil(sortedContacts.length / itemsPerPage) }, (_, i) => (
                <button
                  key={i + 1}
                  onClick={() => paginate(i + 1)}
                  className={`page-item ${currentPage === i + 1 ? 'active' : ''}`}
                >
                  {i + 1}
                </button>
              ))}
            </div>
          </div>
        )}
        
        <Modal
          isOpen={modalState.details.isOpen}
          onClose={() => closeModal('details')}
          title="Detalhes do Contato"
        >
          {modalState.details.contact && (
            <div>
              <p><strong>Nome:</strong> {modalState.details.contact.name}</p>
              <p><strong>Username:</strong> {modalState.details.contact.username}</p>
              <p><strong>Telefone:</strong> {modalState.details.contact.phone}</p>
              <p><strong>Email:</strong> {modalState.details.contact.email}</p>
              <p><strong>Idioma:</strong> {modalState.details.contact.language}</p>
              <p><strong>Criado em:</strong> {new Date(modalState.details.contact.created_at).toLocaleString()}</p>
              <p><strong>Atualizado em:</strong> {new Date(modalState.details.contact.updated_at).toLocaleString()}</p>
            </div>
          )}
        </Modal>

        <Modal
          isOpen={modalState.verify.isOpen}
          onClose={() => closeModal('verify')}
          title="Resultado da Verificação"
        >
          {modalState.verify.result && (
            <p>
              {modalState.verify.result.is_member ? 'O contato é membro do grupo.' : 'O contato não é membro do grupo.'}
            </p>
          )}
        </Modal>

        <Modal
          isOpen={modalState.addGroup.isOpen}
          onClose={() => closeModal('addGroup')}
          title="Adicionar ao Grupo"
        >
          {modalState.addGroup.contact && (
            <div>
              <p>Tem certeza que deseja adicionar {modalState.addGroup.contact.name} ao grupo?</p>
              <button onClick={() => confirmAddGroup(modalState.addGroup.contact)} className="btn btn-success">Sim</button>
              <button onClick={() => closeModal('addGroup')} className="btn btn-secondary">Não</button>
            </div>
          )}
        </Modal>

        <Modal
          isOpen={modalState.removeGroup.isOpen}
          onClose={() => closeModal('removeGroup')}
          title="Remover do Grupo"
        >
          {modalState.removeGroup.contact && (
            <div>
              <p>Tem certeza que deseja remover {modalState.removeGroup.contact.name} do grupo?</p>
              <button onClick={() => confirmRemoveGroup(modalState.removeGroup.contact)} className="btn btn-danger">Sim</button>
              <button onClick={() => closeModal('removeGroup')} className="btn btn-secondary">Não</button>
            </div>
          )}
        </Modal>
        
        <Modal
          isOpen={modalState.block.isOpen}
          onClose={() => closeModal('block')}
          title="Bloquear Contato"
        >
          {modalState.block.contact && (
            <div>
              <p>Tem certeza que deseja {modalState.block.contact.is_blocked ? 'desbloquear' : 'bloquear'} {modalState.block.contact.name}?</p>
              <button onClick={() => confirmBlock(modalState.block.contact)} className="btn btn-danger">Sim</button>
              <button onClick={() => closeModal('block')} className="btn btn-secondary">Não</button>
            </div>
          )}
        </Modal>

      </div>
    </Layout>
  );
};

export default Contacts;
