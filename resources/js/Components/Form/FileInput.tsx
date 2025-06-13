import React, { useState, useRef, ComponentProps } from 'react';
import { fileSize } from '@/utils';
import { Omit } from 'lodash';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface FileInputProps extends Omit<ComponentProps<'input'>, 'onChange'> {
  label?: string;
  error?: string;
  required?: boolean;
  onChange?: (file: File | null) => void;
}

export default function FileInput({
  name,
  label,
  error,
  required = false,
  onChange
}: FileInputProps) {
  // Theme setup with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  const fileInput = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);

  function handleBrowse() {
    fileInput?.current?.click();
  }

  function handleRemove() {
    setFile(null);
    onChange?.(null);
  }

  function handleChange(e: React.FormEvent<HTMLInputElement>) {
    const files = e.currentTarget?.files as FileList;
    const file = files[0] || null;

    setFile(file);
    onChange?.(file);
  }

  return (
    <div>
      {label && (
        <label className={cn(
          'block text-sm font-medium mb-1',
          t.text.secondary
        )}>
          {label}
          {required && <span className={cn("ml-1", t.status.error.split(' ')[0])}>*</span>}
        </label>
      )}
      <div
        className={cn(
          'w-full rounded-xl transition-all duration-200 p-0 overflow-hidden',
          t.input.base,
          error ? t.input.error : ''
        )}
      >
        <input
          id={name}
          ref={fileInput}
          type="file"
          className="hidden"
          onChange={handleChange}
        />
        {!file && (
          <div className="p-2">
            <BrowseButton text="Browse" onClick={handleBrowse} theme={theme} />
          </div>
        )}
        {file && (
          <div className="flex items-center justify-between p-2">
            <div className={cn('flex-1 pr-1', t.text.primary)}>
              {file?.name}
              <span className={cn('ml-1 text-xs', t.text.muted)}>
                ({fileSize(file?.size)})
              </span>
            </div>
            <BrowseButton text="Remove" onClick={handleRemove} theme={theme} />
          </div>
        )}
      </div>
      {error && (
        <p className={cn('mt-1 text-sm', t.status.error.split(' ')[0])}>
          {error}
        </p>
      )}
    </div>
  );
}

interface BrowseButtonProps extends ComponentProps<'button'> {
  text: string;
  theme: 'dark' | 'light';
}

function BrowseButton({ text, theme, onClick, ...props }: BrowseButtonProps) {
  const t = themes[theme];

  return (
    <button
      {...props}
      type="button"
      className={cn(
        'px-4 py-1.5 text-xs font-medium rounded-lg transition-all duration-200',
        t.button.secondary.base,
        t.button.secondary.hover
      )}
      onClick={onClick}
    >
      {text}
    </button>
  );
}
