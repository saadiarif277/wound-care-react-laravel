import { useState, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { usePrevious } from 'react-use';
import SelectInput from '@/Components/Form/SelectInput';
import pickBy from 'lodash/pickBy';
import { ChevronDown, Search } from 'lucide-react';
import FieldGroup from '@/Components/Form/FieldGroup';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

export default function FilterBar() {
  const { filters } = usePage<{
    filters: { role?: string; search?: string; trashed?: string };
  }>().props;

  const [opened, setOpened] = useState(false);
  
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const [values, setValues] = useState({
    role: filters.role || '', // role is used only on users page
    search: filters.search || '',
    trashed: filters.trashed || ''
  });

  const prevValues = usePrevious(values);

  function reset() {
    setValues({
      role: '',
      search: '',
      trashed: ''
    });
  }

  useEffect(() => {
    // https://reactjs.org/docs/hooks-faq.html#how-to-get-the-previous-props-or-state
    if (prevValues) {
      const query = Object.keys(pickBy(values)).length ? pickBy(values) : {};

      router.get(route(route().current() as string), query, {
        replace: true,
        preserveState: true
      });
    }
  }, [values]);

  function handleChange(
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) {
    const name = e.target.name;
    const value = e.target.value;

    setValues(values => ({
      ...values,
      [name]: value
    }));

    if (opened) setOpened(false);
  }

  return (
    <div className="flex items-center w-full max-w-md mr-4">
      <div className={cn("relative flex rounded-xl overflow-hidden", t.glass.base)}>
        <div
          style={{ top: '100%' }}
          className={`absolute ${opened ? '' : 'hidden'} z-40`}
        >
          <div
            onClick={() => setOpened(false)}
            className="fixed inset-0 z-20 bg-black/50 backdrop-blur-sm"
          />
          <div className={cn(
            "relative z-30 w-64 px-4 py-6 mt-2 rounded-xl space-y-4",
            t.glass.card,
            theme === 'dark' ? 'shadow-2xl shadow-black/40' : 'shadow-lg'
          )}>
            {filters.hasOwnProperty('role') && (
              <FieldGroup label="Role" name="role">
                <SelectInput
                  name="role"
                  value={values.role}
                  onChange={handleChange}
                  options={[
                    { value: '', label: '' },
                    { value: 'user', label: 'User' },
                    { value: 'owner', label: 'Owner' }
                  ]}
                />
              </FieldGroup>
            )}
            <FieldGroup label="Trashed" name="trashed">
              <SelectInput
                name="trashed"
                value={values.trashed}
                onChange={handleChange}
                options={[
                  { value: '', label: '' },
                  { value: 'with', label: 'With Trashed' },
                  { value: 'only', label: 'Only Trashed' }
                ]}
              />
            </FieldGroup>
          </div>
        </div>
        <button
          onClick={() => setOpened(true)}
          aria-label="Open filter options"
          className={cn(
            "px-4 md:px-6 transition-all duration-200",
            t.glass.hover,
            theme === 'dark' ? 'border-r border-white/10' : 'border-r border-gray-200',
            "focus:outline-none focus:ring-2 focus:ring-[#1925c3]/50 focus:z-10"
          )}
        >
          <div className="flex items-center">
            <span className={cn("hidden md:inline", t.text.secondary)}>Filter</span>
            <ChevronDown size={14} strokeWidth={3} className={cn("md:ml-2", t.text.secondary)} />
          </div>
        </button>
        <div className="relative flex-1">
          <Search className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4", t.text.muted)} />
          <input
            type="text"
            name="search"
            id="filter-search"
            aria-label="Search filter"
            placeholder="Search…"
            autoComplete="off"
            value={values.search}
            onChange={handleChange}
            className={cn(
              "w-full pl-10 pr-4 py-2 bg-transparent",
              t.text.primary,
              "placeholder:" + t.text.muted.replace('text-', 'text-opacity-'),
              "focus:outline-none focus:ring-2 focus:ring-[#1925c3]/50",
              "transition-all duration-200"
            )}
          />
        </div>
      </div>
      <button
        onClick={reset}
        className={cn(
          "ml-3 text-sm transition-colors",
          t.text.secondary,
          theme === 'dark' ? 'hover:text-white focus:text-blue-400' : 'hover:text-gray-900 focus:text-blue-600',
          "focus:outline-none"
        )}
        type="button"
      >
        Reset
      </button>
    </div>
  );
}
