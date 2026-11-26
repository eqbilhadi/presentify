import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

export function usePermission() {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];

    const permissionSet = useMemo(() => new Set(permissions), [permissions]);

    const can = useCallback(
        (permission: string): boolean => permissionSet.has(permission),
        [permissionSet],
    );

    const canAny = useCallback(
        (...required: string[]): boolean =>
            required.some((permission) => permissionSet.has(permission)),
        [permissionSet],
    );

    const canAll = useCallback(
        (...required: string[]): boolean =>
            required.every((permission) => permissionSet.has(permission)),
        [permissionSet],
    );

    return {
        permissions,
        can,
        canAny,
        canAll,
    };
}
