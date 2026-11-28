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
    style: string;
    base_color: string;
    theme_color: string;
    primary: string | null;
    accent: string | null;
    chart_color: string;
    heading_font: string;
    body_font: string;
    radius: string | null;
    menu_style: string;
    menu_accent: string;
};

export type TenantThemePayload = {
    config: TenantThemeConfig | null;
    css_variables: Record<string, string>;
    dark_css_variables: Record<string, string>;
    font_stylesheet_url: string | null;
    preset_code: string | null;
    data_attributes: {
        style: string;
        menu_style: string;
        menu_accent: string;
    } | null;
};

export type TenantThemePreset = {
    key: string;
    label: string;
    description: string;
    style: string;
    base_color: string;
    theme_color: string;
    primary: string;
    accent: string;
    chart_color: string;
    heading_font: string;
    body_font: string;
    radius: string;
    menu_style: string;
    menu_accent: string;
};

export type TenantThemeStyleOption = {
    key: string;
    label: string;
    description: string;
    default_radius: string;
};

export type TenantThemeBaseColorOption = {
    key: string;
    label: string;
    swatch: string;
    light: Record<string, string>;
    dark: Record<string, string>;
};

export type TenantThemeColorOption = {
    key: string;
    label: string;
    primary: string;
    accent: string;
};

export type TenantThemeChartColorOption = {
    key: string;
    label: string;
    swatch: string;
    light: Record<string, string>;
    dark: Record<string, string>;
};

export type TenantThemeFontOption = {
    key: string;
    label: string;
    category: string;
    google_family: string;
    css_stack: string;
};

export type TenantThemeRadiusOption = {
    value: string;
    label: string;
};

export type TenantThemeMenuOption = {
    key: string;
    label: string;
    description: string;
};

export type TenantThemeDefaults = {
    style: string;
    base_color: string;
    theme_color: string;
    primary: string;
    accent: string;
    chart_color: string;
    heading_font: string;
    body_font: string;
    radius: string;
    menu_style: string;
    menu_accent: string;
};

export type TenantThemeEditorData = TenantThemePayload & {
    presets: TenantThemePreset[];
    style_options: TenantThemeStyleOption[];
    base_color_options: TenantThemeBaseColorOption[];
    theme_color_options: TenantThemeColorOption[];
    chart_color_options: TenantThemeChartColorOption[];
    font_options: TenantThemeFontOption[];
    radius_options: TenantThemeRadiusOption[];
    menu_style_options: TenantThemeMenuOption[];
    menu_accent_options: TenantThemeMenuOption[];
    defaults: TenantThemeDefaults;
};

export type TerminologyDefinition = {
    key: string;
    label: string;
    description: string;
    default: string;
    placeholder: string;
    value: string;
};

export type TerminologyPreset = {
    key: string;
    label: string;
    description: string;
    terms: Record<string, string>;
};

export type TenantTerminologyEditorData = {
    terms: Record<string, string>;
    defaults: Record<string, string>;
    definitions: TerminologyDefinition[];
    presets: TerminologyPreset[];
    is_customized: boolean;
};
