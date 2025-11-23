import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import CreateBot from './pages/CreateBot';
import UpdateBot from './pages/UpdateBot';
import WelcomeMessage from './pages/WelcomeMessage';
import PaymentPlans from './pages/PaymentPlans';
import Redirect from './pages/Redirect';
import Administrators from './pages/Administrators';
import Groups from './pages/Groups';
import Marketing from './pages/Marketing';
import Alerts from './pages/Alerts';
import Downsell from './pages/Downsell';
import Contacts from './pages/Contacts';
import Users from './pages/Users';
import Logs from './pages/Logs';
import PaymentCycles from './pages/PaymentCycles';
import PaymentGatewayConfigs from './pages/PaymentGatewayConfigs';
import GroupManagement from './pages/GroupManagement';
import Billing from './pages/Billing';
import UserGroups from './pages/UserGroups';
import './App.css';

function App() {
  return (
    <AuthProvider>
      <Router>
        <div className="App">
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <Dashboard />
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
            <Route
              path="/bot/welcome"
              element={
                <ProtectedRoute>
                  <WelcomeMessage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/bot/payment-plans"
              element={
                <ProtectedRoute>
                  <PaymentPlans />
                </ProtectedRoute>
              }
            />
            <Route
              path="/bot/redirect"
              element={
                <ProtectedRoute>
                  <Redirect />
                </ProtectedRoute>
              }
            />
            <Route
              path="/bot/administrators"
              element={
                <ProtectedRoute>
                  <Administrators />
                </ProtectedRoute>
              }
            />
            <Route
              path="/bot/groups"
              element={
                <ProtectedRoute>
                  <Groups />
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
          </Routes>
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;

