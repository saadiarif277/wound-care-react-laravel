import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import QuickRequestErrorBoundary, { StepErrorBoundary, withErrorBoundary, useErrorHandler } from '../ErrorBoundary';

// Component that throws an error
const ThrowError = ({ shouldThrow }: { shouldThrow: boolean }) => {
  if (shouldThrow) {
    throw new Error('Test error');
  }
  return <div>No error</div>;
};

// Component that uses error handler hook
const ComponentWithErrorHandler = () => {
  const { throwError, resetError } = useErrorHandler();
  
  return (
    <div>
      <button onClick={() => throwError(new Error('Hook error'))}>Throw Error</button>
      <button onClick={resetError}>Reset Error</button>
    </div>
  );
};

describe('QuickRequestErrorBoundary', () => {
  // Suppress console.error for these tests
  const originalError = console.error;
  beforeAll(() => {
    console.error = jest.fn();
  });
  afterAll(() => {
    console.error = originalError;
  });

  test('renders children when there is no error', () => {
    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <div>Test content</div>
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Test content')).toBeInTheDocument();
  });

  test('renders error UI when error is thrown', () => {
    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Oops! Something went wrong')).toBeInTheDocument();
    expect(screen.getByText(/We encountered an unexpected error/)).toBeInTheDocument();
  });

  test('displays step name when provided', () => {
    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary stepName="Patient Information">
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText(/Error occurred in:/)).toBeInTheDocument();
    expect(screen.getByText('Patient Information')).toBeInTheDocument();
  });

  test('shows error details in development mode', () => {
    const originalEnv = process.env.NODE_ENV;
    process.env.NODE_ENV = 'development';

    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Test error')).toBeInTheDocument();
    expect(screen.getByText('Error Details (Development Only)')).toBeInTheDocument();

    process.env.NODE_ENV = originalEnv;
  });

  test('hides error details in production mode', () => {
    const originalEnv = process.env.NODE_ENV;
    process.env.NODE_ENV = 'production';

    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.queryByText('Test error')).not.toBeInTheDocument();
    expect(screen.queryByText('Error Details (Development Only)')).not.toBeInTheDocument();

    process.env.NODE_ENV = originalEnv;
  });

  test('try again button resets error state', () => {
    const { rerender } = render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Oops! Something went wrong')).toBeInTheDocument();

    // Click try again
    fireEvent.click(screen.getByText('Try Again'));

    // Rerender with no error
    rerender(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={false} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('No error')).toBeInTheDocument();
  });

  test('go back button triggers history back', () => {
    const mockBack = jest.fn();
    Object.defineProperty(window, 'history', {
      writable: true,
      value: { back: mockBack },
    });

    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    fireEvent.click(screen.getByText('Go Back'));
    expect(mockBack).toHaveBeenCalled();
  });

  test('displays custom fallback when provided', () => {
    const customFallback = <div>Custom error message</div>;

    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary fallback={customFallback}>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Custom error message')).toBeInTheDocument();
  });

  test('calls onReset callback when provided', () => {
    const onReset = jest.fn();

    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary onReset={onReset}>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    fireEvent.click(screen.getByText('Try Again'));
    expect(onReset).toHaveBeenCalled();
  });

  test('shows multiple errors warning after threshold', () => {
    const { rerender } = render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ThrowError shouldThrow={true} />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    // Simulate multiple errors by clicking try again and erroring again
    for (let i = 0; i < 3; i++) {
      fireEvent.click(screen.getByText('Try Again'));
      rerender(
        <BrowserRouter>
          <QuickRequestErrorBoundary>
            <ThrowError shouldThrow={true} />
          </QuickRequestErrorBoundary>
        </BrowserRouter>
      );
    }

    expect(screen.getByText(/Multiple errors detected/)).toBeInTheDocument();
  });
});

describe('StepErrorBoundary', () => {
  const originalError = console.error;
  beforeAll(() => {
    console.error = jest.fn();
  });
  afterAll(() => {
    console.error = originalError;
  });

  test('renders with step name', () => {
    render(
      <BrowserRouter>
        <StepErrorBoundary stepName="Test Step">
          <div>Step content</div>
        </StepErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Step content')).toBeInTheDocument();
  });

  test('passes step name to error boundary', () => {
    render(
      <BrowserRouter>
        <StepErrorBoundary stepName="Test Step">
          <ThrowError shouldThrow={true} />
        </StepErrorBoundary>
      </BrowserRouter>
    );

    expect(screen.getByText('Test Step')).toBeInTheDocument();
  });
});

describe('withErrorBoundary HOC', () => {
  const originalError = console.error;
  beforeAll(() => {
    console.error = jest.fn();
  });
  afterAll(() => {
    console.error = originalError;
  });

  test('wraps component with error boundary', () => {
    const TestComponent = () => <div>Test component</div>;
    const WrappedComponent = withErrorBoundary(TestComponent, 'Test HOC');

    render(
      <BrowserRouter>
        <WrappedComponent />
      </BrowserRouter>
    );

    expect(screen.getByText('Test component')).toBeInTheDocument();
  });

  test('catches errors in wrapped component', () => {
    const ErrorComponent = () => {
      throw new Error('HOC error');
    };
    const WrappedComponent = withErrorBoundary(ErrorComponent, 'Test HOC');

    render(
      <BrowserRouter>
        <WrappedComponent />
      </BrowserRouter>
    );

    expect(screen.getByText('Oops! Something went wrong')).toBeInTheDocument();
    expect(screen.getByText('Test HOC')).toBeInTheDocument();
  });
});

describe('useErrorHandler hook', () => {
  const originalError = console.error;
  beforeAll(() => {
    console.error = jest.fn();
  });
  afterAll(() => {
    console.error = originalError;
  });

  test('throws error when throwError is called', () => {
    render(
      <BrowserRouter>
        <QuickRequestErrorBoundary>
          <ComponentWithErrorHandler />
        </QuickRequestErrorBoundary>
      </BrowserRouter>
    );

    fireEvent.click(screen.getByText('Throw Error'));
    expect(screen.getByText('Oops! Something went wrong')).toBeInTheDocument();
  });
});