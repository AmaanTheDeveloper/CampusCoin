import { SVGAttributes } from 'react';

/**
 * Campus Coin mark — simple coin identity for light/dark contexts.
 */
export default function AppLogoIcon({ className, ...props }: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden="true" {...props}>
            <circle cx="20" cy="20" r="18" fill="currentColor" opacity="0.12" />
            <circle cx="20" cy="20" r="14" fill="none" stroke="currentColor" strokeWidth="2.25" />
            <path
                d="M20 10.5v19M15.5 15.25c1.1-1.35 2.55-2 4.5-2 2.85 0 4.75 1.45 4.75 3.55 0 1.85-1.15 2.95-3.85 3.55l-2.3.5c-2.2.5-3.35 1.45-3.35 3.25 0 2.05 1.85 3.5 4.95 3.5 2.05 0 3.6-.7 4.75-2.05"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.25"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
