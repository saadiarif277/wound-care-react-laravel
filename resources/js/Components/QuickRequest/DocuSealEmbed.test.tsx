import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { DocuSealEmbed } from './DocuSealEmbed';

// Mock window.DocuSeal
const mockDocuSeal = {
    builder: jest.fn(() => ({
        mount: jest.fn(),
        unmount: jest.fn(),
        on: jest.fn()
    }))
};

// Mock global fetch
const mockFetch = jest.fn();
global.fetch = mockFetch;

describe('DocuSealEmbed', () => {
    let originalDocuSeal: any;

    beforeEach(() => {
        // Save original window.DocuSeal
        originalDocuSeal = window.DocuSeal;
        // Set up mock
        (window as any).DocuSeal = mockDocuSeal;
        // Clear all mocks
        jest.clearAllMocks();
        // Reset fetch mock
        mockFetch.mockReset();
    });

    afterEach(() => {
        // Restore original window.DocuSeal
        window.DocuSeal = originalDocuSeal;
    });

    describe('Successful Initialization', () => {
        it('should fetch builder token and initialize DocuSeal builder', async () => {
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn()
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://custom.docuseal.com'
                })
            });

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                    className="custom-class"
                />
            );

            // Check loading state
            expect(screen.getByText('Loading DocuSeal builder...')).toBeInTheDocument();

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            manufacturer_id: 'manufacturer-1',
                            product_code: 'A6234'
                        })
                    })
                );
            });

            await waitFor(() => {
                expect(mockDocuSeal.builder).toHaveBeenCalledWith({
                    token: 'test-builder-token',
                    host: 'https://custom.docuseal.com'
                });
                expect(mockBuilder.mount).toHaveBeenCalledWith('docuseal-container');
            });
        });

        it('should use default DocuSeal URL when not provided', async () => {
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn()
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token'
                    // No builderUrl provided
                })
            });

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                />
            );

            await waitFor(() => {
                expect(mockDocuSeal.builder).toHaveBeenCalledWith({
                    token: 'test-builder-token',
                    host: 'https://docuseal.com'
                });
            });
        });

        it('should include CSRF token when available', async () => {
            // Add CSRF token meta tag
            const metaTag = document.createElement('meta');
            metaTag.name = 'csrf-token';
            metaTag.content = 'test-csrf-token';
            document.head.appendChild(metaTag);

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                />
            );

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        headers: expect.objectContaining({
                            'X-CSRF-TOKEN': 'test-csrf-token'
                        })
                    })
                );
            });

            // Clean up
            document.head.removeChild(metaTag);
        });
    });

    describe('Error Handling', () => {
        it('should display error and call onError when fetch fails', async () => {
            const mockOnError = jest.fn();

            mockFetch.mockResolvedValueOnce({
                ok: false,
                status: 500,
                text: async () => 'Internal Server Error'
            });

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                    onError={mockOnError}
                />
            );

            await waitFor(() => {
                expect(screen.getByText('Error loading DocuSeal builder. Please try again.')).toBeInTheDocument();
                expect(mockOnError).toHaveBeenCalledWith(
                    new Error('Failed to generate builder token: Internal Server Error')
                );
            });
        });

        it('should handle network errors', async () => {
            const mockOnError = jest.fn();

            mockFetch.mockRejectedValueOnce(new Error('Network error'));

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                    onError={mockOnError}
                />
            );

            await waitFor(() => {
                expect(screen.getByText('Error loading DocuSeal builder. Please try again.')).toBeInTheDocument();
                expect(mockOnError).toHaveBeenCalledWith(new Error('Network error'));
            });
        });

        it('should handle DocuSeal not being loaded', () => {
            // Remove DocuSeal from window
            delete (window as any).DocuSeal;

            const mockOnError = jest.fn();

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                    onError={mockOnError}
                />
            );

            expect(screen.getByText('DocuSeal is not loaded. Please refresh the page.')).toBeInTheDocument();
            expect(mockOnError).toHaveBeenCalledWith(
                new Error('DocuSeal is not loaded')
            );
        });

        it('should handle builder initialization errors', async () => {
            const mockOnError = jest.fn();
            
            // Mock builder to throw error
            mockDocuSeal.builder.mockImplementation(() => {
                throw new Error('Builder initialization failed');
            });

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                    onError={mockOnError}
                />
            );

            await waitFor(() => {
                expect(screen.getByText('Error loading DocuSeal builder. Please try again.')).toBeInTheDocument();
                expect(mockOnError).toHaveBeenCalledWith(
                    new Error('Builder initialization failed')
                );
            });
        });
    });

    describe('Component Lifecycle', () => {
        it('should clean up builder on unmount', async () => {
            const mockBuilder = {
                mount: jest.fn(),
                unmount: jest.fn(),
                on: jest.fn()
            };
            mockDocuSeal.builder.mockReturnValue(mockBuilder);

            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            const { unmount } = render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                />
            );

            await waitFor(() => {
                expect(mockBuilder.mount).toHaveBeenCalled();
            });

            unmount();

            expect(mockBuilder.unmount).toHaveBeenCalled();
        });

        it('should not call unmount if builder was never initialized', () => {
            // Remove DocuSeal from window
            delete (window as any).DocuSeal;

            const { unmount } = render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                />
            );

            // Should not throw error when unmounting
            expect(() => unmount()).not.toThrow();
        });
    });

    describe('Props and Styling', () => {
        it('should apply custom className', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            const { container } = render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                    className="custom-styling-class"
                />
            );

            await waitFor(() => {
                const embedContainer = container.querySelector('.custom-styling-class');
                expect(embedContainer).toBeInTheDocument();
            });
        });

        it('should apply default className when not provided', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            const { container } = render(
                <DocuSealEmbed
                    manufacturerId="manufacturer-1"
                    productCode="A6234"
                />
            );

            await waitFor(() => {
                const embedContainer = container.querySelector('.w-full.h-full');
                expect(embedContainer).toBeInTheDocument();
            });
        });
    });

    describe('API Request Details', () => {
        it('should send correct request body', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            render(
                <DocuSealEmbed
                    manufacturerId="test-manufacturer-id"
                    productCode="test-product-code"
                />
            );

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        body: JSON.stringify({
                            manufacturer_id: 'test-manufacturer-id',
                            product_code: 'test-product-code'
                        })
                    })
                );
            });
        });

        it('should handle empty product code', async () => {
            mockFetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    builderToken: 'test-builder-token',
                    builderUrl: 'https://docuseal.com'
                })
            });

            render(
                <DocuSealEmbed
                    manufacturerId="test-manufacturer-id"
                    productCode=""
                />
            );

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledWith(
                    '/api/v1/quick-request/docuseal/generate-builder-token',
                    expect.objectContaining({
                        body: JSON.stringify({
                            manufacturer_id: 'test-manufacturer-id',
                            product_code: ''
                        })
                    })
                );
            });
        });
    });
});