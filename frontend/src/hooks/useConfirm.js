import { useState, useCallback } from 'react';
import ConfirmDialog from '../components/ConfirmDialog';

export const useConfirm = () => {
  const [dialog, setDialog] = useState({
    isOpen: false,
    title: null,
    message: '',
    type: 'confirm',
    confirmText: 'Confirmar',
    cancelText: 'Cancelar',
    onConfirm: null,
  });

  const confirm = useCallback((options) => {
    return new Promise((resolve) => {
      setDialog({
        isOpen: true,
        title: options.title || null,
        message: options.message || '',
        type: options.type || 'confirm',
        confirmText: options.confirmText || 'Confirmar',
        cancelText: options.cancelText || 'Cancelar',
        onConfirm: () => {
          setDialog((prev) => ({ ...prev, isOpen: false }));
          resolve(true);
        },
        onCancel: () => {
          setDialog((prev) => ({ ...prev, isOpen: false }));
          resolve(false);
        },
      });
    });
  }, []);

  const close = useCallback(() => {
    setDialog((prev) => ({ ...prev, isOpen: false }));
  }, []);

  const DialogComponent = () => (
    <ConfirmDialog
      isOpen={dialog.isOpen}
      onClose={close}
      onConfirm={dialog.onConfirm}
      onCancel={dialog.onCancel}
      title={dialog.title}
      message={dialog.message}
      type={dialog.type}
      confirmText={dialog.confirmText}
      cancelText={dialog.cancelText}
    />
  );

  return { confirm, DialogComponent };
};

export default useConfirm;

