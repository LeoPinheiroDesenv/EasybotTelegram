import { useCallback } from 'react';
import { useConfirm } from './useConfirm';

export const useAlert = () => {
  const { confirm, DialogComponent } = useConfirm();

  const alert = useCallback((message, title = 'Atenção', type = 'info') => {
    return confirm({
      message,
      title,
      type,
      confirmText: 'OK',
      cancelText: '',
    });
  }, [confirm]);

  return { alert, DialogComponent };
};

export default useAlert;

