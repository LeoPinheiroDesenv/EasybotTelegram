import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import PrivateRoute from './components/PrivateRoute';
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
                <PrivateRoute>
                  <Dashboard />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/create"
              element={
                <PrivateRoute>
                  <CreateBot />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/update/:id"
              element={
                <PrivateRoute>
                  <UpdateBot />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/welcome"
              element={
                <PrivateRoute>
                  <WelcomeMessage />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/payment-plans"
              element={
                <PrivateRoute>
                  <PaymentPlans />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/redirect"
              element={
                <PrivateRoute>
                  <Redirect />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/administrators"
              element={
                <PrivateRoute>
                  <Administrators />
                </PrivateRoute>
              }
            />
            <Route
              path="/bot/groups"
              element={
                <PrivateRoute>
                  <Groups />
                </PrivateRoute>
              }
            />
            <Route
              path="/results/contacts"
              element={
                <PrivateRoute>
                  <Contacts />
                </PrivateRoute>
              }
            />
            <Route
              path="/marketing"
              element={
                <PrivateRoute>
                  <Marketing />
                </PrivateRoute>
              }
            />
            <Route
              path="/marketing/alerts"
              element={
                <PrivateRoute>
                  <Alerts />
                </PrivateRoute>
              }
            />
            <Route
              path="/marketing/downsell"
              element={
                <PrivateRoute>
                  <Downsell />
                </PrivateRoute>
              }
            />
            <Route
              path="/users"
              element={
                <PrivateRoute>
                  <Users />
                </PrivateRoute>
              }
            />
            <Route
              path="/logs"
              element={
                <PrivateRoute>
                  <Logs />
                </PrivateRoute>
              }
            />
            <Route
              path="/settings/payment-cycles"
              element={
                <PrivateRoute>
                  <PaymentCycles />
                </PrivateRoute>
              }
            />
            <Route
              path="/settings/payment-gateways"
              element={
                <PrivateRoute>
                  <PaymentGatewayConfigs />
                </PrivateRoute>
              }
            />
          </Routes>
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;

