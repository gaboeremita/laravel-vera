import { useState } from 'react';
import SchemaField from './SchemaField.jsx';

export default function SchemaForm({ schema, config, onChange }) {
    const [mode, setMode] = useState('form');
    const [jsonValue, setJsonValue] = useState('');
    const [jsonError, setJsonError] = useState(null);

    const primary = schema.filter((p) => !p.section || p.section === 'primary');
    const advanced = schema.filter((p) => p.section === 'advanced');
    const [advancedOpen, setAdvancedOpen] = useState(false);

    const handleFieldChange = (name, value) => {
        const current = typeof config === 'object' && config ? config : {};
        if (value === undefined) {
            const { [name]: _removed, ...rest } = current;
            onChange(rest);
            return;
        }
        onChange({ ...current, [name]: value });
    };

    const handleSwitchMode = (next) => {
        if (next === 'json') {
            setJsonValue(JSON.stringify(config ?? {}, null, 2));
            setJsonError(null);
        }
        setMode(next);
    };

    const handleJsonChange = (raw) => {
        setJsonValue(raw);
        try {
            const parsed = JSON.parse(raw);
            setJsonError(null);
            onChange(parsed);
        } catch {
            setJsonError('Invalid JSON');
        }
    };

    const configObj = typeof config === 'object' && config ? config : {};

    return (
        <div className="space-y-3">
            {/* Mode toggle */}
            <div className="flex justify-end gap-1">
                {['form', 'json'].map((m) => (
                    <button
                        key={m}
                        onClick={() => handleSwitchMode(m)}
                        className={`text-[0.65rem] tracking-[0.1em] uppercase px-3 py-1 border transition-colors cursor-pointer ${
                            mode === m
                                ? 'border-accent text-accent bg-accent/10'
                                : 'border-line-1 text-fg-3 hover:border-fg-3'
                        }`}
                    >
                        {m === 'form' ? 'Form' : 'JSON'}
                    </button>
                ))}
            </div>

            {mode === 'json' ? (
                <div>
                    <textarea
                        value={jsonValue}
                        onChange={(e) => handleJsonChange(e.target.value)}
                        rows={6}
                        className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors resize-none font-mono text-xs"
                    />
                    {jsonError && (
                        <p className="text-danger text-[0.65rem] tracking-[0.05em] mt-1">{jsonError}</p>
                    )}
                </div>
            ) : (
                <div className="space-y-3">
                    {/* Primary params */}
                    {primary.map((param) => (
                        <SchemaField
                            key={param.name}
                            param={param}
                            value={configObj[param.name]}
                            onChange={(v) => handleFieldChange(param.name, v)}
                        />
                    ))}

                    {/* Advanced params */}
                    {advanced.length > 0 && (
                        <div className="border-t border-line-1 pt-3">
                            <button
                                onClick={() => setAdvancedOpen(!advancedOpen)}
                                className="flex items-center gap-2 text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase cursor-pointer hover:text-accent transition-colors"
                            >
                                <span>{advancedOpen ? '▼' : '▶'}</span>
                                Advanced
                            </button>

                            {advancedOpen && (
                                <div className="space-y-3 mt-3">
                                    {advanced.map((param) => (
                                        <SchemaField
                                            key={param.name}
                                            param={param}
                                            value={configObj[param.name]}
                                            onChange={(v) => handleFieldChange(param.name, v)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
