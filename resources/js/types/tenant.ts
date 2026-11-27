export type TenantProfile = {
    id: string;
    name: string;
    slug: string;
    contact_email: string | null;
    phone: string | null;
    address: string | null;
    website: string | null;
    tagline: string | null;
    logo_url: string | null;
    favicon_url: string | null;
    is_active: boolean;
    domains: string[];
};

export type BrandingAssetType = 'logo' | 'favicon';
