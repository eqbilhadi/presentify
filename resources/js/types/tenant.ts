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

export type TenantThemeConfig = {
    primary: string | null;
    accent: string | null;
    radius: string | null;
};

export type TenantThemePayload = {
    config: TenantThemeConfig | null;
    css_variables: Record<string, string>;
    dark_css_variables: Record<string, string>;
};

export type TenantThemePreset = {
    key: string;
    label: string;
    description: string;
    primary: string;
    accent: string;
    radius: string;
};

export type TenantThemeRadiusOption = {
    value: string;
    label: string;
};

export type TenantThemeEditorData = TenantThemePayload & {
    presets: TenantThemePreset[];
    radius_options: TenantThemeRadiusOption[];
    defaults: {
        primary: string;
        accent: string;
        radius: string;
    };
};
