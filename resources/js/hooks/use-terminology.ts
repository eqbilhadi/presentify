import { usePage } from '@inertiajs/react';

export function useTerminologyMap(): Record<string, string> {
    const { tenancy } = usePage().props;

    return tenancy?.terminology ?? {};
}

export function useTerminology(key: string, defaultText?: string): string {
    const dictionary = useTerminologyMap();
    const resolved = dictionary[key];

    if (typeof resolved === 'string' && resolved.trim() !== '') {
        return resolved;
    }

    return defaultText ?? key;
}
