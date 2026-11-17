export interface GenerationSummary {
    id: number;
    prompt: string;
    size: string;
    quality: string;
    status: "pending" | "queued" | "processing" | "succeeded" | "failed";
    estimated_charge_usd: string;
    provider_cost_usd: string | null;
    actual_charge_usd: string | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    error_code: string | null;
    error_message: string | null;
    created_at: string;
}

export interface GenerationPage {
    data: GenerationSummary[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
