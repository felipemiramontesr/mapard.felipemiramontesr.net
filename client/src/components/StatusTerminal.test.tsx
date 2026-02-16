import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import StatusTerminal from './StatusTerminal';
import '@testing-library/jest-dom';

// Mock Capacitor Browser
vi.mock('@capacitor/browser', () => ({
    Browser: {
        open: vi.fn(),
    },
}));

// Mock Framer Motion to avoid animation issues in tests
vi.mock('framer-motion', () => ({
    motion: {
        div: ({ children, className, onClick }: { children: React.ReactNode, className?: string, onClick?: () => void }) => (
            <div className={className} onClick={onClick}>{children}</div>
        ),
    },
    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

describe('StatusTerminal Component', () => {
    const mockLogs = [
        { id: 1, message: 'Initializing...', type: 'info' as const, timestamp: '2023-01-01T12:00:00' },
        { id: 2, message: 'Error detected', type: 'error' as const, timestamp: '2023-01-01T12:00:01' },
    ];

    it('should not render when isVisible is false', () => {
        const { container } = render(
            <StatusTerminal logs={mockLogs} isVisible={false} />
        );
        expect(container).toBeEmptyDOMElement();
    });

    it('should render logs when isVisible is true', () => {
        render(<StatusTerminal logs={mockLogs} isVisible={true} />);

        expect(screen.getByText('Initializing...')).toBeInTheDocument();
        expect(screen.getByText('Error detected')).toBeInTheDocument();
    });

    it('should show reset button when scan is complete', () => {
        const completeLogs = [
            ...mockLogs,
            { id: 3, message: 'Scan Complete', type: 'success' as const, timestamp: '2023-01-01T12:00:02' }
        ];
        const onResetMock = vi.fn();

        render(
            <StatusTerminal
                logs={completeLogs}
                isVisible={true}
                onReset={onResetMock}
                resultUrl="http://example.com/report.pdf"
            />
        );

        // Check for buttons text
        expect(screen.getByText(/EJECUTAR ANÁLISIS/i)).toBeInTheDocument();
        expect(screen.getByText(/DESCARGAR DOSSIER/i)).toBeInTheDocument();

        // Click reset
        fireEvent.click(screen.getByText(/EJECUTAR ANÁLISIS/i));
        expect(onResetMock).toHaveBeenCalledTimes(1);
    });
});
