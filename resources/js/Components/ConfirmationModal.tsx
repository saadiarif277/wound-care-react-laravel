import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { FiX } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { cn } from '@/lib/utils';

interface ConfirmationModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  confirmText?: string;
  cancelText?: string;
  isLoading?: boolean;
  children: React.ReactNode;
}

export default function ConfirmationModal({
  isOpen,
  onClose,
  onConfirm,
  title,
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  isLoading = false,
  children
}: ConfirmationModalProps) {
  const { theme = 'dark' } = useTheme();
  const t = themes[theme];

  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className={cn(
                "w-full max-w-md transform overflow-hidden rounded-2xl p-6 text-left align-middle shadow-xl transition-all",
                t.glass.card
              )}>
                <div className="flex items-center justify-between mb-4">
                  <Dialog.Title
                    as="h3"
                    className={cn("text-lg font-medium leading-6", t.text.primary)}
                  >
                    {title}
                  </Dialog.Title>
                  <button
                    onClick={onClose}
                    className={cn(
                      "p-2 rounded-lg transition-colors",
                      t.glass.frost,
                      "hover:bg-white/10"
                    )}
                  >
                    <FiX className={cn("w-4 h-4", t.text.secondary)} />
                  </button>
                </div>

                <div className="mt-4">
                  {children}
                </div>

                <div className="mt-6 flex justify-end space-x-3">
                  <button
                    type="button"
                    className={cn(
                      "px-4 py-2 rounded-lg font-medium transition-all",
                      t.button.secondary.base,
                      t.button.secondary.hover
                    )}
                    onClick={onClose}
                    disabled={isLoading}
                  >
                    {cancelText}
                  </button>
                  <button
                    type="button"
                    className={cn(
                      "px-4 py-2 rounded-lg font-medium transition-all",
                      isLoading
                        ? "bg-gray-300 text-gray-500 cursor-not-allowed"
                        : `${t.button.primary.base} ${t.button.primary.hover}`
                    )}
                    onClick={onConfirm}
                    disabled={isLoading}
                  >
                    {isLoading ? 'Processing...' : confirmText}
                  </button>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
}