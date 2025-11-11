import React, { useState } from 'react';
import Sidebar from './Sidebar';
import Header from './Header';
import './Layout.css';

const Layout = ({ children }) => {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const toggleSidebar = () => {
    setSidebarOpen(!sidebarOpen);
  };

  const closeSidebar = () => {
    setSidebarOpen(false);
  };

  return (
    <div className="layout">
      <Sidebar isOpen={sidebarOpen} onClose={closeSidebar} />
      <Header onMenuClick={toggleSidebar} />
      {sidebarOpen && <div className="sidebar-overlay" onClick={closeSidebar}></div>}
      <main className="main-content">
        {children}
      </main>
    </div>
  );
};

export default Layout;

