import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { GoogleOAuthProvider } from '@react-oauth/google';
import ProtectedRoute from './components/ProtectedRoute';
import SignInPage from './pages/SignInPage';
import RegisterAdmin from './pages/RegisterAdmin';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import CreateBot from './pages/CreateBot';
import UpdateBot from './pages/UpdateBot';
import WelcomeMessage from './pages/WelcomeMessage';
import PaymentPlans from './pages/PaymentPlans';
import RedirectButtons from './pages/RedirectButtons';
import BotCommands from './pages/BotCommands';
import BotAdministrators from './pages/BotAdministrators';
import BotTelegramGroups from './pages/BotTelegramGroups';
import BotFather from './pages/BotFather';
import Marketing from './pages/Marketing';
import Alerts from './pages/Alerts';
import Downsell from './pages/Downsell';
import Contacts from './pages/Contacts';
import ContactDetails from './pages/ContactDetails';
import Users from './pages/Users';
import Logs from './pages/Logs';
import PaymentCycles from './pages/PaymentCycles';
import PaymentGatewayConfigs from './pages/PaymentGatewayConfigs';
import GroupManagement from './pages/GroupManagement';
import Billing from './pages/Billing';
import UserGroups from './pages/UserGroups';
import SecuritySettings from './pages/SecuritySettings';
import FtpManager from './pages/FtpManager';
import CardPayment from './pages/CardPayment';
import StorageSettings from './pages/StorageSettings';
import ArtisanCommands from './pages/ArtisanCommands';
import CronJobs from './pages/CronJobs';
import LaravelLogs from './pages/LaravelLogs';
import ManageBot from './pages/ManageBot';
import BotList from './pages/BotList';
import PaymentStatus from './pages/PaymentStatus';
import Profile from './pages/Profile';
import './App.css';

const googleClientId = process.env.REACT_APP_GOOGLE_CLIENT_ID;

function App() {
  return (
    <GoogleOAuthProvider clientId={googleClientId}>
      <AuthProvider>
        <Router>
          <div className="App">
            <Routes>
              <Route path="/login" element={<SignInPage />} />
              <Route path="/register-admin" element={<RegisterAdmin />} />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPassword />} />
              <Route
                path="/"
                element={
                  <ProtectedRoute>
                    <Dashboard />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/bot/list"
                element={
                  <ProtectedRoute>
                    <BotList />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/bot/create"
                element={
                  <ProtectedRoute>
                    <CreateBot />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/bot/update/:id"
                element={
                  <ProtectedRoute>
                    <UpdateBot />
                  </ProtectedRoute>
                }
              />
              {/* Rotas do ManageBot com abas */}
              <Route
                path="/bot/manage/:botId"
                element={
                  <ProtectedRoute>
                    <ManageBot />
                  </ProtectedRoute>
                }
              >
                <Route
                  index
                  element={
                    <ProtectedRoute>
                      <UpdateBot />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="settings"
                  element={
                    <ProtectedRoute>
                      <UpdateBot />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="welcome"
                  element={
                    <ProtectedRoute>
                      <WelcomeMessage />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="payment-plans"
                  element={
                    <ProtectedRoute>
                      <PaymentPlans />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="redirect"
                  element={
                    <ProtectedRoute>
                      <RedirectButtons />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="commands"
                  element={
                    <ProtectedRoute>
                      <BotCommands />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="administrators"
                  element={
                    <ProtectedRoute>
                      <BotAdministrators />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="telegram-groups"
                  element={
                    <ProtectedRoute>
                      <BotTelegramGroups />
                    </ProtectedRoute>
                  }
                />
                <Route
                  path="botfather"
                  element={
                    <ProtectedRoute>
                      <BotFather />
                    </ProtectedRoute>
                  }
                />
              </Route>
              {/* Rotas antigas mantidas para compatibilidade */}
              <Route
                path="/bot/welcome/:botId"
                element={
                  <ProtectedRoute>
                    <WelcomeMessage />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/results/contacts"
                element={
                  <ProtectedRoute>
                    <Contacts />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/results/contacts/:id"
                element={
                  <ProtectedRoute>
                    <ContactDetails />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/marketing"
                element={
                  <ProtectedRoute>
                    <Marketing />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/marketing/alerts"
                element={
                  <ProtectedRoute>
                    <Alerts />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/marketing/downsell"
                element={
                  <ProtectedRoute>
                    <Downsell />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/payment-status/:botId?"
                element={
                  <ProtectedRoute>
                    <PaymentStatus />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/users"
                element={
                  <ProtectedRoute>
                    <Users />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/user-groups"
                element={
                  <ProtectedRoute>
                    <UserGroups />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/logs"
                element={
                  <ProtectedRoute>
                    <Logs />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/payment-cycles"
                element={
                  <ProtectedRoute>
                    <PaymentCycles />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/payment-gateways"
                element={
                  <ProtectedRoute>
                    <PaymentGatewayConfigs />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/security"
                element={
                  <ProtectedRoute>
                    <SecuritySettings />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/bot/:botId/group-management"
                element={
                  <ProtectedRoute>
                    <GroupManagement />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/billing"
                element={
                  <ProtectedRoute>
                    <Billing />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/ftp"
                element={
                  <ProtectedRoute>
                    <FtpManager />
                  </ProtectedRoute>
                }
              />
              {/* Rota pública de pagamento */}
              <Route
                path="/payment/card/:token"
                element={<CardPayment />}
              />
              <Route
                path="/settings/storage"
                element={
                  <ProtectedRoute>
                    <StorageSettings />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/artisan"
                element={
                  <ProtectedRoute>
                    <ArtisanCommands />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/cron-jobs"
                element={
                  <ProtectedRoute>
                    <CronJobs />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/laravel-logs"
                element={
                  <ProtectedRoute>
                    <LaravelLogs />
                  </ProtectedRoute>
                }
              />
              <Route
                path="/settings/profile"
                element={
                  <ProtectedRoute>
                    <Profile />
                  </ProtectedRoute>
                }
              />
            </Routes>
          </div>
        </Router>
      </AuthProvider>
    </GoogleOAuthProvider>
  );
}

export default App;
