import { Monitor, Moon, Sun } from 'lucide-react';
import type { ComponentType } from 'react';

import { DropdownMenu, DropdownMenuContent, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTheme, type ThemePreference } from '@/lib/theme';
import { cn } from '@/lib/utils';

interface ThemeOption {
    value: ThemePreference;
    label: string;
    icon: ComponentType<{ className?: string }>;
}

const SYSTEM_OPTION: ThemeOption = { value: 'system', label: 'Como el sistema', icon: Monitor };

const OPTIONS: ThemeOption[] = [
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
    SYSTEM_OPTION,
];

/** Selector de tema claro, oscuro o el del sistema. */
export function ThemeToggle({ className, showLabel = false }: { className?: string; showLabel?: boolean }) {
    const [preference, setPreference] = useTheme();
    const current = OPTIONS.find((option) => option.value === preference) ?? SYSTEM_OPTION;
    const Icon = current.icon;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label={`Tema: ${current.label}`}
                className={cn(
                    'flex items-center gap-3 rounded-lg text-sidebar-foreground transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring',
                    showLabel ? 'w-full px-3 py-2.5 text-sm' : 'size-10 justify-center',
                    className,
                )}
            >
                <Icon className="size-5" />
                {showLabel ? `Tema: ${current.label}` : null}
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="end">
                <DropdownMenuRadioGroup value={preference} onValueChange={(value) => setPreference(value as ThemePreference)}>
                    {OPTIONS.map((option) => (
                        <DropdownMenuRadioItem key={option.value} value={option.value}>
                            <option.icon className="size-4" />
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
