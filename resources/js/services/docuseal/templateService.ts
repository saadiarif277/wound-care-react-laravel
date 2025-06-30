import axios from 'axios';

export interface ManufacturerField {
  slug: string;
  name: string;
  type: 'text' | 'checkbox' | 'select' | 'radio' | 'date' | 'textarea' | 'number' | 'signature';
  required: boolean;
  description?: string;
  options?: Array<{ value: string; label: string }>;
  default_value?: any;
}

export interface ManufacturerTemplateResponse {
  manufacturer: string;
  template_id: string | null;
  fields: ManufacturerField[];
}

class DocuSealTemplateService {
  private baseUrl = '/api/v1/docuseal/templates';

  /**
   * Get template fields for a specific manufacturer
   */
  async getManufacturerFields(manufacturer: string): Promise<ManufacturerTemplateResponse> {
    try {
      const response = await axios.get<ManufacturerTemplateResponse>(
        `${this.baseUrl}/manufacturer-fields/${encodeURIComponent(manufacturer)}`
      );
      return response.data;
    } catch (error) {
      console.error('Error fetching manufacturer fields:', error);
      // Return empty fields on error
      return {
        manufacturer,
        template_id: null,
        fields: []
      };
    }
  }

  /**
   * Sync templates from DocuSeal
   */
  async syncTemplates(): Promise<{ success: boolean; templates: any[] }> {
    try {
      const response = await axios.post(`${this.baseUrl}/sync`);
      return response.data;
    } catch (error) {
      console.error('Error syncing templates:', error);
      throw error;
    }
  }

  /**
   * Get all templates
   */
  async getTemplates(page: number = 1): Promise<any> {
    try {
      const response = await axios.get(`${this.baseUrl}/templates`, {
        params: { page }
      });
      return response.data;
    } catch (error) {
      console.error('Error fetching templates:', error);
      throw error;
    }
  }

  /**
   * Extract fields from uploaded PDF
   */
<<<<<<< HEAD
  async extractFields(file: File, templateId: string, manufacturerId: string): Promise<any> {
=======
  async extractFields(file: File, templateId: string, manufacturer: string): Promise<any> {
>>>>>>> origin/provider-side
    try {
      const formData = new FormData();
      formData.append('pdf', file);
      formData.append('template_id', templateId);
<<<<<<< HEAD
      formData.append('manufacturer_id', manufacturerId);
=======
      formData.append('manufacturer', manufacturer);
>>>>>>> origin/provider-side

      const response = await axios.post(`${this.baseUrl}/extract-fields`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      return response.data;
    } catch (error) {
      console.error('Error extracting fields:', error);
      throw error;
    }
  }

  /**
   * Update field mappings
   */
  async updateMappings(templateId: string, mappings: any[]): Promise<any> {
    try {
      const response = await axios.put(`${this.baseUrl}/templates/${templateId}/mappings`, {
        mappings
      });
      return response.data;
    } catch (error) {
      console.error('Error updating mappings:', error);
      throw error;
    }
  }
}

export default new DocuSealTemplateService();
