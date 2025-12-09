import React, { createContext, useContext } from 'react';

const ManageBotContext = createContext(false);

export const ManageBotProvider = ({ children }) => {
  return (
    <ManageBotContext.Provider value={true}>
      {children}
    </ManageBotContext.Provider>
  );
};

export const useManageBot = () => {
  return useContext(ManageBotContext);
};

