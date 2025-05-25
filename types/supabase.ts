export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  public: {
    Tables: {
      accounts: {
        Row: {
          created_at: string | null
          id: number
          name: string
          updated_at: string | null
        }
        Insert: {
          created_at?: string | null
          id?: number
          name: string
          updated_at?: string | null
        }
        Update: {
          created_at?: string | null
          id?: number
          name?: string
          updated_at?: string | null
        }
        Relationships: []
      }
      commission_payouts: {
        Row: {
          approved_at: string | null
          approved_by: number | null
          created_at: string | null
          deleted_at: string | null
          id: number
          notes: string | null
          payment_reference: string | null
          period_end: string
          period_start: string
          processed_at: string | null
          rep_id: number
          status: string
          total_amount: number
          updated_at: string | null
        }
        Insert: {
          approved_at?: string | null
          approved_by?: number | null
          created_at?: string | null
          deleted_at?: string | null
          id?: number
          notes?: string | null
          payment_reference?: string | null
          period_end: string
          period_start: string
          processed_at?: string | null
          rep_id: number
          status?: string
          total_amount: number
          updated_at?: string | null
        }
        Update: {
          approved_at?: string | null
          approved_by?: number | null
          created_at?: string | null
          deleted_at?: string | null
          id?: number
          notes?: string | null
          payment_reference?: string | null
          period_end?: string
          period_start?: string
          processed_at?: string | null
          rep_id?: number
          status?: string
          total_amount?: number
          updated_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "commission_payouts_approved_by_foreign"
            columns: ["approved_by"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_payouts_rep_id_foreign"
            columns: ["rep_id"]
            isOneToOne: false
            referencedRelation: "msc_sales_reps"
            referencedColumns: ["id"]
          },
        ]
      }
      commission_records: {
        Row: {
          amount: number
          approved_at: string | null
          approved_by: number | null
          calculation_date: string
          created_at: string | null
          deleted_at: string | null
          id: number
          notes: string | null
          order_id: number
          order_item_id: number
          parent_rep_id: number | null
          payout_id: number | null
          percentage_rate: number
          rep_id: number
          status: string
          type: string
          updated_at: string | null
        }
        Insert: {
          amount: number
          approved_at?: string | null
          approved_by?: number | null
          calculation_date: string
          created_at?: string | null
          deleted_at?: string | null
          id?: number
          notes?: string | null
          order_id: number
          order_item_id: number
          parent_rep_id?: number | null
          payout_id?: number | null
          percentage_rate: number
          rep_id: number
          status?: string
          type: string
          updated_at?: string | null
        }
        Update: {
          amount?: number
          approved_at?: string | null
          approved_by?: number | null
          calculation_date?: string
          created_at?: string | null
          deleted_at?: string | null
          id?: number
          notes?: string | null
          order_id?: number
          order_item_id?: number
          parent_rep_id?: number | null
          payout_id?: number | null
          percentage_rate?: number
          rep_id?: number
          status?: string
          type?: string
          updated_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "commission_records_approved_by_foreign"
            columns: ["approved_by"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_order_id_foreign"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_order_item_id_foreign"
            columns: ["order_item_id"]
            isOneToOne: false
            referencedRelation: "order_items"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_parent_rep_id_foreign"
            columns: ["parent_rep_id"]
            isOneToOne: false
            referencedRelation: "msc_sales_reps"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_payout_id_foreign"
            columns: ["payout_id"]
            isOneToOne: false
            referencedRelation: "commission_payouts"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_rep_id_foreign"
            columns: ["rep_id"]
            isOneToOne: false
            referencedRelation: "msc_sales_reps"
            referencedColumns: ["id"]
          },
        ]
      }
      commission_rules: {
        Row: {
          created_at: string | null
          deleted_at: string | null
          description: string | null
          id: number
          is_active: boolean
          percentage_rate: number
          target_id: number
          target_type: string
          updated_at: string | null
          valid_from: string
          valid_to: string | null
        }
        Insert: {
          created_at?: string | null
          deleted_at?: string | null
          description?: string | null
          id?: number
          is_active?: boolean
          percentage_rate: number
          target_id: number
          target_type: string
          updated_at?: string | null
          valid_from: string
          valid_to?: string | null
        }
        Update: {
          created_at?: string | null
          deleted_at?: string | null
          description?: string | null
          id?: number
          is_active?: boolean
          percentage_rate?: number
          target_id?: number
          target_type?: string
          updated_at?: string | null
          valid_from?: string
          valid_to?: string | null
        }
        Relationships: []
      }
      contacts: {
        Row: {
          account_id: number
          address: string | null
          city: string | null
          country: string | null
          created_at: string | null
          deleted_at: string | null
          email: string | null
          first_name: string
          id: number
          last_name: string
          organization_id: number | null
          phone: string | null
          postal_code: string | null
          region: string | null
          updated_at: string | null
        }
        Insert: {
          account_id: number
          address?: string | null
          city?: string | null
          country?: string | null
          created_at?: string | null
          deleted_at?: string | null
          email?: string | null
          first_name: string
          id?: number
          last_name: string
          organization_id?: number | null
          phone?: string | null
          postal_code?: string | null
          region?: string | null
          updated_at?: string | null
        }
        Update: {
          account_id?: number
          address?: string | null
          city?: string | null
          country?: string | null
          created_at?: string | null
          deleted_at?: string | null
          email?: string | null
          first_name?: string
          id?: number
          last_name?: string
          organization_id?: number | null
          phone?: string | null
          postal_code?: string | null
          region?: string | null
          updated_at?: string | null
        }
        Relationships: []
      }
      facilities: {
        Row: {
          active: boolean
          address: string
          business_hours: Json | null
          city: string
          created_at: string | null
          deleted_at: string | null
          email: string | null
          facility_type: string
          id: number
          name: string
          npi: string | null
          organization_id: number
          phone: string | null
          state: string
          updated_at: string | null
          zip_code: string
        }
        Insert: {
          active?: boolean
          address: string
          business_hours?: Json | null
          city: string
          created_at?: string | null
          deleted_at?: string | null
          email?: string | null
          facility_type: string
          id?: number
          name: string
          npi?: string | null
          organization_id: number
          phone?: string | null
          state: string
          updated_at?: string | null
          zip_code: string
        }
        Update: {
          active?: boolean
          address?: string
          business_hours?: Json | null
          city?: string
          created_at?: string | null
          deleted_at?: string | null
          email?: string | null
          facility_type?: string
          id?: number
          name?: string
          npi?: string | null
          organization_id?: number
          phone?: string | null
          state?: string
          updated_at?: string | null
          zip_code?: string
        }
        Relationships: [
          {
            foreignKeyName: "facilities_organization_id_foreign"
            columns: ["organization_id"]
            isOneToOne: false
            referencedRelation: "organizations"
            referencedColumns: ["id"]
          },
        ]
      }
      failed_jobs: {
        Row: {
          connection: string
          exception: string
          failed_at: string
          id: number
          payload: string
          queue: string
          uuid: string
        }
        Insert: {
          connection: string
          exception: string
          failed_at?: string
          id?: number
          payload: string
          queue: string
          uuid: string
        }
        Update: {
          connection?: string
          exception?: string
          failed_at?: string
          id?: number
          payload?: string
          queue?: string
          uuid?: string
        }
        Relationships: []
      }
      migrations: {
        Row: {
          batch: number
          id: number
          migration: string
        }
        Insert: {
          batch: number
          id?: number
          migration: string
        }
        Update: {
          batch?: number
          id?: number
          migration?: string
        }
        Relationships: []
      }
      msc_products: {
        Row: {
          available_sizes: Json | null
          category: string | null
          category_id: number | null
          commission_rate: number | null
          created_at: string | null
          deleted_at: string | null
          description: string | null
          document_urls: Json | null
          graph_type: string | null
          id: number
          image_url: string | null
          is_active: boolean
          manufacturer: string | null
          manufacturer_id: number | null
          name: string
          national_asp: number | null
          price_per_sq_cm: number | null
          q_code: string | null
          sku: string
          updated_at: string | null
        }
        Insert: {
          available_sizes?: Json | null
          category?: string | null
          category_id?: number | null
          commission_rate?: number | null
          created_at?: string | null
          deleted_at?: string | null
          description?: string | null
          document_urls?: Json | null
          graph_type?: string | null
          id?: number
          image_url?: string | null
          is_active?: boolean
          manufacturer?: string | null
          manufacturer_id?: number | null
          name: string
          national_asp?: number | null
          price_per_sq_cm?: number | null
          q_code?: string | null
          sku: string
          updated_at?: string | null
        }
        Update: {
          available_sizes?: Json | null
          category?: string | null
          category_id?: number | null
          commission_rate?: number | null
          created_at?: string | null
          deleted_at?: string | null
          description?: string | null
          document_urls?: Json | null
          graph_type?: string | null
          id?: number
          image_url?: string | null
          is_active?: boolean
          manufacturer?: string | null
          manufacturer_id?: number | null
          name?: string
          national_asp?: number | null
          price_per_sq_cm?: number | null
          q_code?: string | null
          sku?: string
          updated_at?: string | null
        }
        Relationships: []
      }
      msc_sales_reps: {
        Row: {
          commission_rate_direct: number
          created_at: string | null
          deleted_at: string | null
          email: string
          id: number
          is_active: boolean
          name: string
          parent_rep_id: number | null
          phone: string | null
          sub_rep_parent_share_percentage: number
          territory: string | null
          updated_at: string | null
        }
        Insert: {
          commission_rate_direct?: number
          created_at?: string | null
          deleted_at?: string | null
          email: string
          id?: number
          is_active?: boolean
          name: string
          parent_rep_id?: number | null
          phone?: string | null
          sub_rep_parent_share_percentage?: number
          territory?: string | null
          updated_at?: string | null
        }
        Update: {
          commission_rate_direct?: number
          created_at?: string | null
          deleted_at?: string | null
          email?: string
          id?: number
          is_active?: boolean
          name?: string
          parent_rep_id?: number | null
          phone?: string | null
          sub_rep_parent_share_percentage?: number
          territory?: string | null
          updated_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "msc_sales_reps_parent_rep_id_foreign"
            columns: ["parent_rep_id"]
            isOneToOne: false
            referencedRelation: "msc_sales_reps"
            referencedColumns: ["id"]
          },
        ]
      }
      order_items: {
        Row: {
          created_at: string | null
          deleted_at: string | null
          graph_size: string | null
          id: number
          order_id: number
          price: number
          product_id: number
          quantity: number
          total_amount: number
          updated_at: string | null
        }
        Insert: {
          created_at?: string | null
          deleted_at?: string | null
          graph_size?: string | null
          id?: number
          order_id: number
          price: number
          product_id: number
          quantity: number
          total_amount: number
          updated_at?: string | null
        }
        Update: {
          created_at?: string | null
          deleted_at?: string | null
          graph_size?: string | null
          id?: number
          order_id?: number
          price?: number
          product_id?: number
          quantity?: number
          total_amount?: number
          updated_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "order_items_order_id_foreign"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_items_product_id_foreign"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "msc_products"
            referencedColumns: ["id"]
          },
        ]
      }
      orders: {
        Row: {
          created_at: string | null
          credit_terms: string
          date_of_service: string
          deleted_at: string | null
          document_urls: Json | null
          expected_collection_date: string | null
          expected_reimbursement: number
          facility_id: number
          id: number
          msc_commission: number
          msc_commission_structure: number
          notes: string | null
          order_number: string
          patient_fhir_id: string
          payment_status: string
          sales_rep_id: number | null
          status: string
          total_amount: number
          updated_at: string | null
        }
        Insert: {
          created_at?: string | null
          credit_terms?: string
          date_of_service: string
          deleted_at?: string | null
          document_urls?: Json | null
          expected_collection_date?: string | null
          expected_reimbursement?: number
          facility_id: number
          id?: number
          msc_commission?: number
          msc_commission_structure?: number
          notes?: string | null
          order_number: string
          patient_fhir_id: string
          payment_status?: string
          sales_rep_id?: number | null
          status?: string
          total_amount?: number
          updated_at?: string | null
        }
        Update: {
          created_at?: string | null
          credit_terms?: string
          date_of_service?: string
          deleted_at?: string | null
          document_urls?: Json | null
          expected_collection_date?: string | null
          expected_reimbursement?: number
          facility_id?: number
          id?: number
          msc_commission?: number
          msc_commission_structure?: number
          notes?: string | null
          order_number?: string
          patient_fhir_id?: string
          payment_status?: string
          sales_rep_id?: number | null
          status?: string
          total_amount?: number
          updated_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "orders_facility_id_foreign"
            columns: ["facility_id"]
            isOneToOne: false
            referencedRelation: "facilities"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "orders_sales_rep_id_foreign"
            columns: ["sales_rep_id"]
            isOneToOne: false
            referencedRelation: "msc_sales_reps"
            referencedColumns: ["id"]
          },
        ]
      }
      organizations: {
        Row: {
          account_id: number
          address: string | null
          city: string | null
          country: string | null
          created_at: string | null
          deleted_at: string | null
          email: string | null
          id: number
          name: string
          phone: string | null
          postal_code: string | null
          region: string | null
          updated_at: string | null
        }
        Insert: {
          account_id: number
          address?: string | null
          city?: string | null
          country?: string | null
          created_at?: string | null
          deleted_at?: string | null
          email?: string | null
          id?: number
          name: string
          phone?: string | null
          postal_code?: string | null
          region?: string | null
          updated_at?: string | null
        }
        Update: {
          account_id?: number
          address?: string | null
          city?: string | null
          country?: string | null
          created_at?: string | null
          deleted_at?: string | null
          email?: string | null
          id?: number
          name?: string
          phone?: string | null
          postal_code?: string | null
          region?: string | null
          updated_at?: string | null
        }
        Relationships: []
      }
      password_reset_tokens: {
        Row: {
          created_at: string | null
          email: string
          token: string
        }
        Insert: {
          created_at?: string | null
          email: string
          token: string
        }
        Update: {
          created_at?: string | null
          email?: string
          token?: string
        }
        Relationships: []
      }
      personal_access_tokens: {
        Row: {
          abilities: string | null
          created_at: string | null
          expires_at: string | null
          id: number
          last_used_at: string | null
          name: string
          token: string
          tokenable_id: number
          tokenable_type: string
          updated_at: string | null
        }
        Insert: {
          abilities?: string | null
          created_at?: string | null
          expires_at?: string | null
          id?: number
          last_used_at?: string | null
          name: string
          token: string
          tokenable_id: number
          tokenable_type: string
          updated_at?: string | null
        }
        Update: {
          abilities?: string | null
          created_at?: string | null
          expires_at?: string | null
          id?: number
          last_used_at?: string | null
          name?: string
          token?: string
          tokenable_id?: number
          tokenable_type?: string
          updated_at?: string | null
        }
        Relationships: []
      }
      users: {
        Row: {
          account_id: number
          created_at: string | null
          deleted_at: string | null
          email: string
          email_verified_at: string | null
          first_name: string
          id: number
          last_name: string
          owner: boolean
          password: string | null
          photo: string | null
          remember_token: string | null
          updated_at: string | null
        }
        Insert: {
          account_id: number
          created_at?: string | null
          deleted_at?: string | null
          email: string
          email_verified_at?: string | null
          first_name: string
          id?: number
          last_name: string
          owner?: boolean
          password?: string | null
          photo?: string | null
          remember_token?: string | null
          updated_at?: string | null
        }
        Update: {
          account_id?: number
          created_at?: string | null
          deleted_at?: string | null
          email?: string
          email_verified_at?: string | null
          first_name?: string
          id?: number
          last_name?: string
          owner?: boolean
          password?: string | null
          photo?: string | null
          remember_token?: string | null
          updated_at?: string | null
        }
        Relationships: []
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      [_ in never]: never
    }
    Enums: {
      [_ in never]: never
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DefaultSchema = Database[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof Database },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof (Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        Database[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof Database }
  ? (Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      Database[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof Database },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof Database }
  ? Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof Database },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof Database }
  ? Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof Database },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends { schema: keyof Database }
  ? Database[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof Database },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends { schema: keyof Database }
  ? Database[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {},
  },
} as const
