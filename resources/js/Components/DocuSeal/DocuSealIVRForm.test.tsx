import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { DocuSealIVRForm } from './DocuSealIVRForm';
import { router } from '@inertiajs/react';

// Mock Inertia router
jest.mock('@inertiajs/react', () => ({
    router: {
        post: jest.fn()
    }
}));

// Mock window.DocuSeal
const mockDocuSeal = {
    builder: jest.fn(() => ({
        mount: jest.fn(),
        unmount: jest.fn(),
        on: jest.fn()
    }))
};

describe('DocuSealIVRForm', () => {
    let originalDocuSeal: any;

    beforeEach(() => {
        // Save original window.DocuSeal
        originalDocuSeal = window.DocuSeal;
        // Set up mock
        (window as any).DocuSeal = mockDocuSeal;
        // Clear all mocks
        jest.clearAllMocks();
    });

    afterEach(() => {
        // Restore original window.DocuSeal
        window.DocuSeal = originalDocuSeal;
    });

    describe('Manufacturer ID Extraction', () => {
        it('should extract manufacturer ID from Biowound template folder', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                // Verify the extracted manufacturer ID
                expect(data.manufacturer_id).toBe(1);
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockPost).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        manufacturer_id: 1,
                        template_id: '12345',
                        patient_display_id: 'JO123',
                        episode_id: 'episode-123',
                        product_code: 'A6234'
                    }),
                    expect.any(Object)
                );
            });
        });

        it('should extract manufacturer ID from Medlife template folder', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                expect(data.manufacturer_id).toBe(2);
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="67890"
                    templateFolder="Medlife"
                    patientDisplayId="SM456"
                    episodeId="episode-456"
                    productCode="A6235"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockPost).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        manufacturer_id: 2
                    }),
                    expect.any(Object)
                );
            });
        });

        it('should extract manufacturer ID from Extremity Care template folder', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                expect(data.manufacturer_id).toBe(3);
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="11111"
                    templateFolder="Extremity Care"
                    patientDisplayId="EC789"
                    episodeId="episode-789"
                    productCode="A6236"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockPost).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        manufacturer_id: 3
                    }),
                    expect.any(Object)
                );
            });
        });

        it('should use default manufacturer ID for unknown folder', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                expect(data.manufacturer_id).toBe(1); // Default
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="99999"
                    templateFolder="Unknown"
                    patientDisplayId="UN999"
                    episodeId="episode-999"
                    productCode="A6237"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockPost).toHaveBeenCalled();
            });
        });
    });

    describe('Product Code Handling', () => {
        it('should pass product code to API request', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                expect(data.product_code).toBe('A6234');
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockPost).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        product_code: 'A6234'
                    }),
                    expect.any(Object)
                );
            });
        });

        it('should handle missing product code', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                expect(data.product_code).toBeUndefined();
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockPost).toHaveBeenCalled();
            });
        });
    });

    describe('DocuSeal Builder Integration', () => {
        it('should initialize DocuSeal builder with token', async () => {
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn()
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-builder-token',
                        builderUrl: 'https://custom.docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockDocuSeal.builder).toHaveBeenCalledWith({
                    token: 'test-builder-token',
                    host: 'https://custom.docuseal.com'
                });
                expect(mockBuilder.mount).toHaveBeenCalledWith('docuseal-container');
            });
        });

        it('should use default DocuSeal URL if not provided', async () => {
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn()
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-builder-token'
                        // No builderUrl provided
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockDocuSeal.builder).toHaveBeenCalledWith({
                    token: 'test-builder-token',
                    host: 'https://docuseal.com'
                });
            });
        });
    });

    describe('Callback Handling', () => {
        it('should call onComplete when form is submitted', async () => {
            const mockOnComplete = jest.fn();
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn((event, callback) => {
                    if (event === 'submit') {
                        // Simulate form submission
                        setTimeout(() => {
                            callback({ status: 'completed' });
                        }, 100);
                    }
                })
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={mockOnComplete}
                />
            );

            await waitFor(() => {
                expect(mockBuilder.on).toHaveBeenCalledWith('submit', expect.any(Function));
            });

            // Wait for the simulated submission
            await waitFor(() => {
                expect(mockOnComplete).toHaveBeenCalledWith({ status: 'completed' });
            }, { timeout: 200 });
        });
    });

    describe('Error Handling', () => {
        it('should display error message on API failure', async () => {
            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                options.onError({});
            });

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(screen.getByText('Failed to initialize DocuSeal form. Please try again.')).toBeInTheDocument();
            });
        });

        it('should handle DocuSeal not being loaded', () => {
            // Remove DocuSeal from window
            delete (window as any).DocuSeal;

            render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            expect(screen.getByText('DocuSeal is not loaded. Please refresh the page.')).toBeInTheDocument();
        });
    });

    describe('Component Lifecycle', () => {
        it('should clean up DocuSeal builder on unmount', async () => {
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn()
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            const mockPost = router.post as jest.Mock;
            mockPost.mockImplementation((url, data, options) => {
                options.onSuccess({ 
                    data: { 
                        builderToken: 'test-token',
                        builderUrl: 'https://docuseal.com'
                    }
                });
            });

            const { unmount } = render(
                <DocuSealIVRForm
                    templateId="12345"
                    templateFolder="Biowound"
                    patientDisplayId="JO123"
                    episodeId="episode-123"
                    productCode="A6234"
                    onComplete={jest.fn()}
                />
            );

            await waitFor(() => {
                expect(mockBuilder.mount).toHaveBeenCalled();
            });

            unmount();

            expect(mockBuilder.unmount).toHaveBeenCalled();
        });
    });
});