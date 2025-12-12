import React, { useState, useEffect } from 'react';
import Layout from '../components/Layout';
import cronJobService from '../services/cronJobService';
import useConfirm from '../hooks/useConfirm';
import './CronJobs.css';

const CronJobs = () => {
  const { confirm, DialogComponent } = useConfirm();
  const [cronJobs, setCronJobs] = useState([]);
  const [defaultCronJobs, setDefaultCronJobs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingList, setLoadingList] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [showTestModal, setShowTestModal] = useState(false);
  const [editingCronJob, setEditingCronJob] = useState(null);
  const [testResult, setTestResult] = useState(null);
  const [testing, setTesting] = useState(false);
  const [cpanelWarning, setCpanelWarning] = useState(null);
  const [cronJobCommands, setCronJobCommands] = useState(null);
  const [cronJobFrequency, setCronJobFrequency] = useState(null);
  const [copiedCommand, setCopiedCommand] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    endpoint: '',
    method: 'POST',
    frequency: '*/5 * * * *',
    headers: {},
    body: null,
    is_active: true,
  });
  const [selectedTemplate, setSelectedTemplate] = useState('');
  const [selectedFrequency, setSelectedFrequency] = useState('');
  const [baseUrl, setBaseUrl] = useState('');

  // Templates de cron jobs comuns
  const cronJobTemplates = [
    {
      id: 'payments-check',
      name: 'Verificar Pagamentos Pendentes',
      description: 'Verifica pagamentos PIX pendentes e processa aprovações automaticamente',
      endpoint: '/api/payments/check-pending',
      method: 'POST',
      frequency: '*/1 * * * *',
      headers: {
        'Content-Type': 'application/json',
        'X-Payments-Check-Token': '',
      },
      body: { bot_id: null, interval: 30 },
    },
    {
      id: 'pix-expiration',
      name: 'Verificar Expiração de PIX',
      description: 'Verifica PIX que expiraram e notifica os usuários',
      endpoint: '/api/pix/check-expiration',
      method: 'POST',
      frequency: '*/5 * * * *',
      headers: {
        'Content-Type': 'application/json',
        'X-Pix-Check-Token': '',
      },
      body: null,
    },
    {
      id: 'custom-api',
      name: 'Chamada API Customizada',
      description: 'Template para criar um cron job customizado',
      endpoint: '/api/endpoint',
      method: 'POST',
      frequency: '*/5 * * * *',
      headers: {
        'Content-Type': 'application/json',
      },
      body: {},
    },
  ];

  // Opções de frequência pré-definidas
  const frequencyOptions = [
    { value: '* * * * *', label: 'A cada minuto', description: 'Executa a cada 1 minuto' },
    { value: '*/2 * * * *', label: 'A cada 2 minutos', description: 'Executa a cada 2 minutos' },
    { value: '*/5 * * * *', label: 'A cada 5 minutos', description: 'Executa a cada 5 minutos' },
    { value: '*/10 * * * *', label: 'A cada 10 minutos', description: 'Executa a cada 10 minutos' },
    { value: '*/15 * * * *', label: 'A cada 15 minutos', description: 'Executa a cada 15 minutos' },
    { value: '*/30 * * * *', label: 'A cada 30 minutos', description: 'Executa a cada 30 minutos' },
    { value: '0 * * * *', label: 'A cada hora', description: 'Executa no minuto 0 de cada hora' },
    { value: '0 */2 * * *', label: 'A cada 2 horas', description: 'Executa a cada 2 horas' },
    { value: '0 */6 * * *', label: 'A cada 6 horas', description: 'Executa a cada 6 horas' },
    { value: '0 0 * * *', label: 'Diariamente (meia-noite)', description: 'Executa uma vez por dia à meia-noite' },
    { value: '0 0 * * 0', label: 'Semanalmente (domingo)', description: 'Executa todo domingo à meia-noite' },
    { value: '0 0 1 * *', label: 'Mensalmente', description: 'Executa no dia 1 de cada mês à meia-noite' },
    { value: 'custom', label: 'Personalizado', description: 'Defina uma frequência customizada' },
  ];

  // Templates de headers comuns
  const headerTemplates = [
    {
      name: 'JSON API',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    },
    {
      name: 'JSON com Autenticação',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_TOKEN',
      },
    },
    {
      name: 'API Token',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Token': 'YOUR_TOKEN',
      },
    },
  ];

  // Função para obter cron jobs padrão como fallback
  const getFallbackDefaultCronJobs = () => {
    const currentUrl = typeof window !== 'undefined' ? window.location.origin : '';
    return [
      {
        id: null,
        name: 'Verificar Pagamentos Pendentes',
        description: 'Verifica pagamentos PIX pendentes e processa aprovações automaticamente. Deve ser executado a cada 1-2 minutos.',
        endpoint: currentUrl + '/api/payments/check-pending',
        method: 'POST',
        frequency: '*/1 * * * *',
        headers: {
          'Content-Type': 'application/json',
          'X-Payments-Check-Token': '',
        },
        body: { bot_id: null, interval: 30 },
        is_active: true,
        is_system: true,
      },
      {
        id: null,
        name: 'Verificar Expiração de PIX',
        description: 'Verifica PIX que expiraram e notifica os usuários. Deve ser executado a cada 5 minutos.',
        endpoint: currentUrl + '/api/pix/check-expiration',
        method: 'POST',
        frequency: '*/5 * * * *',
        headers: {
          'Content-Type': 'application/json',
          'X-Pix-Check-Token': '',
        },
        body: null,
        is_active: true,
        is_system: true,
      },
    ];
  };

  useEffect(() => {
    // Detecta URL base da aplicação
    const currentUrl = window.location.origin;
    setBaseUrl(currentUrl);
    
    // Carrega cron jobs
    loadCronJobs();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadCronJobs = async () => {
    try {
      setLoadingList(true);
      setError('');
      const response = await cronJobService.getAll();
      
      // Garante que response seja um objeto
      const data = response || {};
      
      if (data.success === false) {
        setError(data.error || 'Erro ao carregar cron jobs');
        // Mesmo com erro, tenta exibir os dados disponíveis
        setCronJobs(data.cron_jobs || []);
        setDefaultCronJobs(data.default_cron_jobs || []);
      } else {
        setCronJobs(data.cron_jobs || []);
        // Se não houver cron jobs padrão na resposta, usa o fallback
        setDefaultCronJobs(data.default_cron_jobs && data.default_cron_jobs.length > 0 
          ? data.default_cron_jobs 
          : getFallbackDefaultCronJobs());
        
        // Se houver warning, mostra como info
        if (data.warning) {
          setSuccess(data.warning);
          setTimeout(() => setSuccess(''), 5000);
        }
      }
    } catch (err) {
      const errorMessage = err.response?.data?.error || err.message || 'Erro ao carregar cron jobs';
      setError(errorMessage);
      
      // Tenta exibir dados parciais se disponíveis
      if (err.response?.data) {
        setCronJobs(err.response.data.cron_jobs || []);
        setDefaultCronJobs(err.response.data.default_cron_jobs && err.response.data.default_cron_jobs.length > 0
          ? err.response.data.default_cron_jobs
          : getFallbackDefaultCronJobs());
      } else {
        setCronJobs([]);
        // Usa fallback se não conseguir carregar nada
        setDefaultCronJobs(getFallbackDefaultCronJobs());
      }
    } finally {
      setLoadingList(false);
    }
  };

  const handleCreateDefault = async (defaultJob) => {
    const confirmed = await confirm({
      message: `Deseja criar o cron job padrão "${defaultJob.name}"?`,
      type: 'info',
    });

    if (!confirmed) return;

    try {
      setLoading(true);
      setError('');
      setSuccess('');
      const response = await cronJobService.createDefault(defaultJob.name);
      const data = response || {};
      
      if (data.success) {
        setSuccess(`Cron job "${defaultJob.name}" criado com sucesso!`);
        
        // Verifica se há aviso do cPanel
        if (data.cpanel_message) {
          setCpanelWarning(data.cpanel_message);
          // Gera comandos se o cron job foi retornado
          if (data.cron_job) {
            setCronJobCommands(generateCommands(data.cron_job));
            setCronJobFrequency(data.cron_job.frequency);
          }
        } else {
          setCpanelWarning(null);
          setCronJobCommands(null);
          setCronJobFrequency(null);
        }
        
        await loadCronJobs();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(data.error || 'Erro ao criar cron job padrão');
        setCpanelWarning(null);
        setCronJobCommands(null);
        setCronJobFrequency(null);
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao criar cron job padrão');
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = () => {
    setEditingCronJob(null);
    setSelectedTemplate('');
    setSelectedFrequency('*/5 * * * *');
    setFormData({
      name: '',
      description: '',
      endpoint: '',
      method: 'POST',
      frequency: '*/5 * * * *',
      headers: {},
      body: null,
      is_active: true,
    });
    setCpanelWarning(null);
    setCronJobCommands(null);
    setCronJobFrequency(null);
    setShowModal(true);
  };

  const handleTemplateSelect = (templateId) => {
    const template = cronJobTemplates.find(t => t.id === templateId);
    if (template) {
      setSelectedTemplate(templateId);
      // Mantém apenas o caminho do endpoint (sem URL base) para edição
      setFormData({
        name: template.name,
        description: template.description,
        endpoint: template.endpoint, // Apenas o caminho, a URL base será adicionada no save
        method: template.method,
        frequency: template.frequency,
        headers: { ...template.headers },
        body: template.body ? JSON.parse(JSON.stringify(template.body)) : null,
        is_active: true,
      });
      // Seleciona a frequência correspondente
      const freqOption = frequencyOptions.find(f => f.value === template.frequency);
      if (freqOption) {
        setSelectedFrequency(template.frequency);
      }
    }
  };

  const handleFrequencySelect = (frequency) => {
    setSelectedFrequency(frequency);
    if (frequency !== 'custom') {
      setFormData({ ...formData, frequency });
    }
  };

  const handleHeaderTemplateSelect = (template) => {
    setFormData({
      ...formData,
      headers: { ...template.headers },
    });
  };

  const handleEdit = (cronJob) => {
    setEditingCronJob(cronJob);
    // Remove a URL base do endpoint para edição
    let endpointPath = cronJob.endpoint;
    if (endpointPath.startsWith(baseUrl)) {
      endpointPath = endpointPath.replace(baseUrl, '');
    }
    
    setSelectedTemplate('');
    const freqOption = frequencyOptions.find(f => f.value === cronJob.frequency);
    setSelectedFrequency(freqOption ? cronJob.frequency : 'custom');
    
    setFormData({
      name: cronJob.name,
      description: cronJob.description || '',
      endpoint: endpointPath,
      method: cronJob.method,
      frequency: cronJob.frequency,
      headers: cronJob.headers || {},
      body: cronJob.body,
      is_active: cronJob.is_active,
    });
    setShowModal(true);
  };

  const handleSave = async () => {
    try {
      setLoading(true);
      setError('');
      setSuccess('');

      // Validação básica
      if (!formData.name || !formData.endpoint || !formData.frequency) {
        setError('Preencha todos os campos obrigatórios');
        return;
      }

      // Garante que o endpoint tenha a URL completa
      let finalEndpoint = formData.endpoint;
      if (!formData.endpoint.startsWith('http://') && !formData.endpoint.startsWith('https://')) {
        // Se começar com /, adiciona a URL base
        if (formData.endpoint.startsWith('/')) {
          finalEndpoint = baseUrl + formData.endpoint;
        } else {
          finalEndpoint = baseUrl + '/' + formData.endpoint;
        }
      }

      const dataToSave = {
        ...formData,
        endpoint: finalEndpoint,
      };

      let response;
      if (editingCronJob) {
        response = await cronJobService.update(editingCronJob.id, dataToSave);
      } else {
        response = await cronJobService.create(dataToSave);
      }

      const data = response || {};
      
      if (data.success) {
        setSuccess(editingCronJob ? 'Cron job atualizado com sucesso!' : 'Cron job criado com sucesso!');
        
        // Verifica se há aviso do cPanel
        if (data.cpanel_message) {
          setCpanelWarning(data.cpanel_message);
          // Gera comandos se o cron job foi retornado
          if (data.cron_job) {
            setCronJobCommands(generateCommands(data.cron_job));
            setCronJobFrequency(data.cron_job.frequency);
          }
        } else {
          setCpanelWarning(null);
          setCronJobCommands(null);
          setCronJobFrequency(null);
        }
        
        setShowModal(false);
        await loadCronJobs();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        const errorMsg = data.errors 
          ? JSON.stringify(data.errors) 
          : (data.error || 'Erro ao salvar cron job');
        setError(errorMsg);
        setCpanelWarning(null);
        setCronJobCommands(null);
        setCronJobFrequency(null);
      }
    } catch (err) {
      const errorMsg = err.response?.data?.errors 
        ? JSON.stringify(err.response.data.errors) 
        : (err.response?.data?.error || err.message || 'Erro ao salvar cron job');
      setError(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (cronJob) => {
    const confirmed = await confirm({
      message: `Tem certeza que deseja deletar o cron job "${cronJob.name}"?`,
      type: 'warning',
    });

    if (!confirmed) return;

    try {
      setLoading(true);
      setError('');
      const response = await cronJobService.delete(cronJob.id);
      const data = response || {};
      
      if (data.success) {
        setSuccess('Cron job deletado com sucesso!');
        await loadCronJobs();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        setError(data.error || 'Erro ao deletar cron job');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.message || 'Erro ao deletar cron job');
    } finally {
      setLoading(false);
    }
  };

  const handleTest = async (cronJob) => {
    try {
      setTesting(true);
      setError('');
      setTestResult(null);
      setShowTestModal(true);
      
      const response = await cronJobService.test(cronJob.id);
      const data = response || {};
      setTestResult(data.test_result || data);
      await loadCronJobs(); // Atualiza estatísticas
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao testar cron job');
      setTestResult({
        success: false,
        error: err.response?.data?.error || 'Erro desconhecido',
      });
    } finally {
      setTesting(false);
    }
  };

  const handleHeaderChange = (key, value) => {
    const newHeaders = { ...formData.headers };
    if (value) {
      newHeaders[key] = value;
    } else {
      delete newHeaders[key];
    }
    setFormData({ ...formData, headers: newHeaders });
  };

  const handleBodyChange = (value) => {
    try {
      const parsed = value ? JSON.parse(value) : null;
      setFormData({ ...formData, body: parsed });
    } catch (e) {
      // Mantém como string se não for JSON válido
      setFormData({ ...formData, body: value });
    }
  };

  const formatFrequency = (frequency) => {
    const parts = frequency.split(' ');
    if (parts.length !== 5) return frequency;
    
    const [minute, hour, day, month, weekday] = parts;
    let desc = '';
    
    if (minute.startsWith('*/')) {
      const interval = minute.substring(2);
      desc = `A cada ${interval} minuto(s)`;
    } else if (minute === '*') {
      desc = 'A cada minuto';
    } else {
      desc = `No minuto ${minute}`;
    }
    
    if (hour !== '*') desc += `, na hora ${hour}`;
    if (day !== '*') desc += `, no dia ${day}`;
    if (month !== '*') desc += `, no mês ${month}`;
    if (weekday !== '*') desc += `, no dia da semana ${weekday}`;
    
    return desc;
  };

  // Função para gerar comandos curl/wget a partir de um cron job
  const generateCommands = (cronJob) => {
    if (!cronJob) return null;

    let curlCommand = `curl -X ${cronJob.method}`;
    let wgetCommand = `wget --quiet --method=${cronJob.method}`;

    // Adiciona headers
    if (cronJob.headers && Object.keys(cronJob.headers).length > 0) {
      Object.entries(cronJob.headers).forEach(([key, value]) => {
        if (value) {
          curlCommand += ` -H "${key}: ${value}"`;
          wgetCommand += ` --header="${key}: ${value}"`;
        }
      });
    }

    // Adiciona body se for POST/PUT
    if (['POST', 'PUT'].includes(cronJob.method) && cronJob.body) {
      const bodyJson = JSON.stringify(cronJob.body);
      curlCommand += ` -d '${bodyJson.replace(/'/g, "'\\''")}'`;
      wgetCommand += ` --body-data='${bodyJson.replace(/'/g, "'\\''")}'`;
    }

    curlCommand += ` --silent --output /dev/null "${cronJob.endpoint}"`;
    wgetCommand += ` --output-document=- "${cronJob.endpoint}" > /dev/null 2>&1`;

    return { curl: curlCommand, wget: wgetCommand };
  };

  // Função para copiar comando para a área de transferência
  const copyToClipboard = async (text, commandType) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedCommand(commandType);
      setTimeout(() => setCopiedCommand(null), 2000);
    } catch (err) {
      // Fallback para navegadores mais antigos
      const textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.opacity = '0';
      document.body.appendChild(textArea);
      textArea.select();
      try {
        document.execCommand('copy');
        setCopiedCommand(commandType);
        setTimeout(() => setCopiedCommand(null), 2000);
      } catch (e) {
        setError('Erro ao copiar comando');
      }
      document.body.removeChild(textArea);
    }
  };

  return (
    <Layout>
      <div className="cron-jobs-container">
        <div className="page-header">
          <h1>Gerenciar Cron Jobs</h1>
          <button className="btn btn-primary" onClick={handleCreate}>
            + Novo Cron Job
          </button>
        </div>

        {error && (
          <div className="alert alert-error">
            {error}
            <button onClick={() => setError('')}>×</button>
          </div>
        )}

        {success && (
          <div className="alert alert-success">
            {success}
            <button onClick={() => setSuccess('')}>×</button>
          </div>
        )}

        {/* Aviso do cPanel com comandos */}
        {cpanelWarning && (
          <div className="cpanel-warning-container">
            <div className="cpanel-warning-header">
              <div className="cpanel-warning-icon">⚠️</div>
              <div className="cpanel-warning-title">
                <h3>Aviso: cPanel não disponível</h3>
                <p className="cpanel-warning-subtitle">O cron job foi criado no banco de dados, mas não foi possível criar automaticamente no cPanel.</p>
              </div>
              <button 
                className="cpanel-warning-close" 
                onClick={() => {
                  setCpanelWarning(null);
                  setCronJobCommands(null);
                }}
              >
                ×
              </button>
            </div>
            <div className="cpanel-warning-body">
              <div className="cpanel-warning-message">
                <p>{cpanelWarning}</p>
              </div>
              {cronJobCommands && (
                <div className="cpanel-commands-section">
                  <h4>Comandos para criar manualmente no cPanel:</h4>
                  
                  <div className="command-block">
                    <div className="command-header">
                      <span className="command-label">Comando cURL:</span>
                      <button
                        className={`btn-copy ${copiedCommand === 'curl' ? 'copied' : ''}`}
                        onClick={() => copyToClipboard(cronJobCommands.curl, 'curl')}
                        title="Copiar comando cURL"
                      >
                        {copiedCommand === 'curl' ? '✓ Copiado!' : '📋 Copiar'}
                      </button>
                    </div>
                    <pre className="command-code">{cronJobCommands.curl}</pre>
                  </div>

                  <div className="command-block">
                    <div className="command-header">
                      <span className="command-label">Comando wget:</span>
                      <button
                        className={`btn-copy ${copiedCommand === 'wget' ? 'copied' : ''}`}
                        onClick={() => copyToClipboard(cronJobCommands.wget, 'wget')}
                        title="Copiar comando wget"
                      >
                        {copiedCommand === 'wget' ? '✓ Copiado!' : '📋 Copiar'}
                      </button>
                    </div>
                    <pre className="command-code">{cronJobCommands.wget}</pre>
                  </div>

                  <div className="cpanel-instructions">
                    <p><strong>Como usar:</strong></p>
                    <ol>
                      <li>Acesse seu cPanel</li>
                      <li>Vá em <strong>Cron Jobs</strong> ou <strong>Tarefas Agendadas</strong></li>
                      <li>Copie um dos comandos acima (curl ou wget)</li>
                      <li>Configure a frequência: <code>{cronJobFrequency || formData.frequency || '* * * * *'}</code></li>
                      <li>Cole o comando no campo de comando</li>
                      <li>Salve o cron job</li>
                    </ol>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {loadingList ? (
          <div className="loading">Carregando cron jobs...</div>
        ) : (
          <>
            {/* Cron Jobs Padrão do Sistema */}
            <div className="cron-jobs-section">
              <h2>Cron Jobs Padrão do Sistema</h2>
              <p className="section-description">
                Estes são os cron jobs recomendados para a aplicação. Clique em "Criar" para adicioná-los ao sistema.
              </p>
              {defaultCronJobs.length > 0 ? (
                <div className="cron-jobs-grid">
                  {defaultCronJobs.map((job, index) => (
                    <div key={index} className="cron-job-card default">
                      <div className="cron-job-header">
                        <h3>{job.name}</h3>
                        <span className="badge badge-system">Sistema</span>
                      </div>
                      <p className="cron-job-description">{job.description}</p>
                      <div className="cron-job-details">
                        <div className="detail-item">
                          <strong>Endpoint:</strong> <code>{job.endpoint}</code>
                        </div>
                        <div className="detail-item">
                          <strong>Método:</strong> {job.method}
                        </div>
                        <div className="detail-item">
                          <strong>Frequência:</strong> <code>{job.frequency}</code>
                        </div>
                        <div className="detail-item">
                          <small>{formatFrequency(job.frequency)}</small>
                        </div>
                      </div>
                      <button
                        className="btn btn-secondary btn-sm"
                        onClick={() => handleCreateDefault(job)}
                        disabled={loading}
                      >
                        Criar
                      </button>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="empty-state">
                  <p>Nenhum cron job padrão disponível no momento.</p>
                </div>
              )}
            </div>

            {/* Cron Jobs Criados */}
            <div className="cron-jobs-section">
              <h2>Cron Jobs Configurados ({cronJobs.length})</h2>
              {cronJobs.length === 0 ? (
                <div className="empty-state">
                  <p>Nenhum cron job configurado ainda.</p>
                  <p>Use os cron jobs padrão acima ou crie um novo.</p>
                </div>
              ) : (
                <div className="cron-jobs-grid">
                  {cronJobs.map((job) => (
                    <div key={job.id} className={`cron-job-card ${job.is_active ? 'active' : 'inactive'}`}>
                      <div className="cron-job-header">
                        <h3>{job.name}</h3>
                        <div className="badges">
                          {job.is_system && (
                            <span className="badge badge-system">Sistema</span>
                          )}
                          <span className={`badge ${job.is_active ? 'badge-success' : 'badge-warning'}`}>
                            {job.is_active ? 'Ativo' : 'Inativo'}
                          </span>
                        </div>
                      </div>
                      {job.description && (
                        <p className="cron-job-description">{job.description}</p>
                      )}
                      <div className="cron-job-details">
                        <div className="detail-item">
                          <strong>Endpoint:</strong> <code>{job.endpoint}</code>
                        </div>
                        <div className="detail-item">
                          <strong>Método:</strong> {job.method}
                        </div>
                        <div className="detail-item">
                          <strong>Frequência:</strong> <code>{job.frequency}</code>
                        </div>
                        <div className="detail-item">
                          <small>{formatFrequency(job.frequency)}</small>
                        </div>
                        {job.last_run_at && (
                          <div className="detail-item">
                            <strong>Última execução:</strong>{' '}
                            {new Date(job.last_run_at).toLocaleString('pt-BR')}
                          </div>
                        )}
                        <div className="detail-item stats">
                          <span>Execuções: {job.run_count}</span>
                          <span className="success">Sucessos: {job.success_count}</span>
                          <span className="error">Erros: {job.error_count}</span>
                        </div>
                      </div>
                      <div className="cron-job-actions">
                        <button
                          className="btn btn-info btn-sm"
                          onClick={() => handleTest(job)}
                          disabled={loading || testing}
                        >
                          Testar
                        </button>
                        <button
                          className="btn btn-secondary btn-sm"
                          onClick={() => handleEdit(job)}
                          disabled={loading || job.is_system}
                        >
                          Editar
                        </button>
                        <button
                          className="btn btn-danger btn-sm"
                          onClick={() => handleDelete(job)}
                          disabled={loading || job.is_system}
                        >
                          Deletar
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}

        {/* Modal de Criar/Editar */}
        {showModal && (
          <div className="modal-overlay" onClick={() => {
            setShowModal(false);
            setCpanelWarning(null);
            setCronJobCommands(null);
          }}>
            <div className="modal-content large" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>{editingCronJob ? 'Editar Cron Job' : 'Novo Cron Job'}</h2>
                <button className="modal-close" onClick={() => {
                  setShowModal(false);
                  setCpanelWarning(null);
                  setCronJobCommands(null);
                }}>×</button>
              </div>
              <div className="modal-body">
                {/* Seletor de Template */}
                {!editingCronJob && (
                  <div className="form-group">
                    <label htmlFor="template">Escolha um Template (Opcional)</label>
                    <select
                      id="template"
                      value={selectedTemplate}
                      onChange={(e) => handleTemplateSelect(e.target.value)}
                      className="template-selector"
                    >
                      <option value="">-- Selecione um template para preencher automaticamente --</option>
                      {cronJobTemplates.map(template => (
                        <option key={template.id} value={template.id}>
                          {template.name} - {template.description}
                        </option>
                      ))}
                    </select>
                    <small className="help-text">
                      Selecione um template para preencher automaticamente todos os campos
                    </small>
                  </div>
                )}

                <div className="form-group">
                  <label htmlFor="name">Nome *</label>
                  <input
                    type="text"
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="Ex: Verificar Pagamentos Pendentes"
                    disabled={editingCronJob?.is_system}
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="description">Descrição</label>
                  <textarea
                    id="description"
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    placeholder="Descreva o que este cron job faz..."
                    rows="3"
                    disabled={editingCronJob?.is_system}
                  />
                </div>

                <div className="form-row">
                  <div className="form-group">
                    <label htmlFor="endpoint">Endpoint *</label>
                    <div className="endpoint-input-group">
                      <input
                        type="text"
                        id="endpoint"
                        value={formData.endpoint}
                        onChange={(e) => setFormData({ ...formData, endpoint: e.target.value })}
                        placeholder="/api/endpoint"
                        disabled={editingCronJob?.is_system}
                        className="endpoint-input"
                      />
                      <span className="endpoint-prefix">{baseUrl}</span>
                    </div>
                    <small className="help-text">
                      Digite apenas o caminho (ex: /api/payments/check-pending). A URL base será adicionada automaticamente.
                    </small>
                  </div>

                  <div className="form-group">
                    <label htmlFor="method">Método HTTP *</label>
                    <select
                      id="method"
                      value={formData.method}
                      onChange={(e) => setFormData({ ...formData, method: e.target.value })}
                      disabled={editingCronJob?.is_system}
                    >
                      <option value="GET">GET</option>
                      <option value="POST">POST</option>
                      <option value="PUT">PUT</option>
                      <option value="DELETE">DELETE</option>
                    </select>
                  </div>
                </div>

                <div className="form-group">
                  <label htmlFor="frequency">Frequência de Execução *</label>
                  <select
                    id="frequency-select"
                    value={selectedFrequency || formData.frequency}
                    onChange={(e) => handleFrequencySelect(e.target.value)}
                    disabled={editingCronJob?.is_system}
                    className="frequency-selector"
                  >
                    {frequencyOptions.map(option => (
                      <option key={option.value} value={option.value}>
                        {option.label} - {option.description}
                      </option>
                    ))}
                  </select>
                  {selectedFrequency === 'custom' && (
                    <input
                      type="text"
                      id="frequency"
                      value={formData.frequency}
                      onChange={(e) => setFormData({ ...formData, frequency: e.target.value })}
                      placeholder="*/5 * * * *"
                      disabled={editingCronJob?.is_system}
                      className="frequency-custom-input"
                    />
                  )}
                  <small className="help-text">
                    Formato: minuto hora dia mês dia-da-semana (ex: */5 * * * * = a cada 5 minutos)
                  </small>
                  {formData.frequency && (
                    <div className="frequency-preview">
                      <strong>Preview:</strong> {formatFrequency(formData.frequency)}
                    </div>
                  )}
                </div>

                <div className="form-group">
                  <label>Headers HTTP</label>
                  {!editingCronJob && (
                    <div className="header-templates">
                      <small className="help-text">Ou use um template de headers:</small>
                      <div className="header-template-buttons">
                        {headerTemplates.map((template, index) => (
                          <button
                            key={index}
                            type="button"
                            className="btn btn-outline btn-sm"
                            onClick={() => handleHeaderTemplateSelect(template)}
                          >
                            {template.name}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                  <div className="headers-editor">
                    {Object.entries(formData.headers || {}).map(([key, value]) => (
                      <div key={key} className="header-row">
                        <input
                          type="text"
                          placeholder="Nome do header (ex: Content-Type)"
                          value={key}
                          onChange={(e) => {
                            const newHeaders = { ...formData.headers };
                            delete newHeaders[key];
                            newHeaders[e.target.value] = value;
                            setFormData({ ...formData, headers: newHeaders });
                          }}
                          disabled={editingCronJob?.is_system}
                          list="common-headers"
                        />
                        <datalist id="common-headers">
                          <option value="Content-Type" />
                          <option value="Authorization" />
                          <option value="X-API-Token" />
                          <option value="X-Payments-Check-Token" />
                          <option value="X-Pix-Check-Token" />
                          <option value="Accept" />
                        </datalist>
                        <input
                          type="text"
                          placeholder="Valor do header"
                          value={value}
                          onChange={(e) => handleHeaderChange(key, e.target.value)}
                          disabled={editingCronJob?.is_system}
                        />
                        <button
                          type="button"
                          className="btn btn-danger btn-xs"
                          onClick={() => {
                            const newHeaders = { ...formData.headers };
                            delete newHeaders[key];
                            setFormData({ ...formData, headers: newHeaders });
                          }}
                          disabled={editingCronJob?.is_system}
                        >
                          ×
                        </button>
                      </div>
                    ))}
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={() => {
                        const newHeaders = { ...formData.headers };
                        newHeaders[''] = '';
                        setFormData({ ...formData, headers: newHeaders });
                      }}
                      disabled={editingCronJob?.is_system}
                    >
                      + Adicionar Header
                    </button>
                  </div>
                </div>

                {(formData.method === 'POST' || formData.method === 'PUT') && (
                  <div className="form-group">
                    <label htmlFor="body">Body (JSON)</label>
                    <textarea
                      id="body"
                      value={typeof formData.body === 'string' ? formData.body : JSON.stringify(formData.body || {}, null, 2)}
                      onChange={(e) => handleBodyChange(e.target.value)}
                      placeholder='{"key": "value"}'
                      rows="5"
                      disabled={editingCronJob?.is_system}
                      className="json-editor"
                    />
                    <small className="help-text">
                      Formato JSON válido. Exemplos: <code>{"{}"}</code> para objeto vazio, <code>null</code> para sem body
                    </small>
                    {formData.body && typeof formData.body === 'object' && (
                      <div className="body-preview">
                        <strong>Preview:</strong> {JSON.stringify(formData.body, null, 2)}
                      </div>
                    )}
                  </div>
                )}

                <div className="form-group">
                  <label className="checkbox-label">
                    <input
                      type="checkbox"
                      checked={formData.is_active}
                      onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                      disabled={editingCronJob?.is_system}
                    />
                    <span>Cron job ativo</span>
                  </label>
                </div>

                {editingCronJob?.is_system && (
                  <div className="alert alert-info">
                    <strong>Nota:</strong> Este é um cron job do sistema. Apenas o status (ativo/inativo) pode ser alterado.
                  </div>
                )}
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setShowModal(false)}>
                  Cancelar
                </button>
                <button className="btn btn-primary" onClick={handleSave} disabled={loading}>
                  {loading ? 'Salvando...' : 'Salvar'}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Modal de Teste */}
        {showTestModal && (
          <div className="modal-overlay" onClick={() => setShowTestModal(false)}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
              <div className="modal-header">
                <h2>Resultado do Teste</h2>
                <button className="modal-close" onClick={() => setShowTestModal(false)}>×</button>
              </div>
              <div className="modal-body">
                {testing ? (
                  <div className="loading">Testando cron job...</div>
                ) : testResult ? (
                  <div>
                    <div className={`test-result ${testResult.success ? 'success' : 'error'}`}>
                      <strong>Status:</strong> {testResult.success ? '✅ Sucesso' : '❌ Erro'}
                    </div>
                    {testResult.status_code && (
                      <div className="test-detail">
                        <strong>Código HTTP:</strong> {testResult.status_code}
                      </div>
                    )}
                    {testResult.duration_ms && (
                      <div className="test-detail">
                        <strong>Tempo de execução:</strong> {testResult.duration_ms}ms
                      </div>
                    )}
                    {testResult.response && (
                      <div className="test-detail">
                        <strong>Resposta:</strong>
                        <pre>{JSON.stringify(testResult.response, null, 2)}</pre>
                      </div>
                    )}
                    {testResult.error && (
                      <div className="test-detail error">
                        <strong>Erro:</strong> {testResult.error}
                      </div>
                    )}
                  </div>
                ) : (
                  <div>Nenhum resultado disponível</div>
                )}
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setShowTestModal(false)}>
                  Fechar
                </button>
              </div>
            </div>
          </div>
        )}

        <DialogComponent />
      </div>
    </Layout>
  );
};

export default CronJobs;
