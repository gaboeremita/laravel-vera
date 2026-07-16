import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

function IntegerFloatField({ param, value, onChange }) {
    const hasRange = param.min !== undefined && param.max !== undefined;
    const step = param.step ?? (param.type === 'float' ? 0.1 : 1);
    const numValue = value ?? param.default ?? '';

    return (
        <div className="space-y-1">
            <div className="flex items-center gap-2">
                <input
                    type="number"
                    value={numValue}
                    min={param.min}
                    max={param.max}
                    step={step}
                    onChange={(e) => onChange(param.type === 'integer' ? parseInt(e.target.value, 10) : parseFloat(e.target.value))}
                    className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
                />
            </div>
            {hasRange && (
                <input
                    type="range"
                    value={numValue === '' ? param.min : numValue}
                    min={param.min}
                    max={param.max}
                    step={step}
                    onChange={(e) => onChange(param.type === 'integer' ? parseInt(e.target.value, 10) : parseFloat(e.target.value))}
                    className="w-full accent-[var(--color-accent)]"
                />
            )}
            {hasRange && (
                <div className="flex justify-between text-fg-3 text-[0.6rem] tracking-[0.05em]">
                    <span>{param.min}</span>
                    <span>{param.max}</span>
                </div>
            )}
        </div>
    );
}

function BooleanField({ param, value, onChange }) {
    const checked = value ?? param.default ?? false;
    return (
        <button
            onClick={() => onChange(!checked)}
            className={`px-3 py-1 text-[0.7rem] tracking-[0.1em] border transition-colors cursor-pointer ${
                checked
                    ? 'border-success text-success bg-success/10'
                    : 'border-line-1 text-fg-3'
            }`}
        >
            {checked ? 'ON' : 'OFF'}
        </button>
    );
}

function EnumField({ param, value, onChange }) {
    return (
        <select
            value={value ?? param.default ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
        >
            {!param.required && <option value="">— unset —</option>}
            {(param.options ?? []).map((opt) => (
                <option key={opt} value={opt}>{opt}</option>
            ))}
        </select>
    );
}

function StringField({ param, value, onChange }) {
    return (
        <input
            type="text"
            value={value ?? param.default ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className="w-full bg-bg-1 border border-line-1 text-accent text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
        />
    );
}

function ObjectField({ param, value, onChange }) {
    const [collapsed, setCollapsed] = useState(true);
    const children = param.children ?? [];
    const childConfig = value ?? {};

    const handleChildChange = (childName, childValue) => {
        onChange({ ...childConfig, [childName]: childValue });
    };

    return (
        <div className="border border-line-1">
            <button
                onClick={() => setCollapsed(!collapsed)}
                className="w-full flex items-center gap-2 px-3 py-2 hover:bg-bg-1 transition-colors cursor-pointer"
            >
                <motion.span
                    animate={{ rotate: collapsed ? 0 : 90 }}
                    transition={{ duration: 0.2 }}
                    className="text-fg-3 text-xs"
                >
                    ▶
                </motion.span>
                <span className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">{param.name}</span>
            </button>

            <AnimatePresence initial={false}>
                {!collapsed && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.2, ease: 'easeOut' }}
                        style={{ overflow: 'hidden' }}
                    >
                        <div className="px-3 pb-3 space-y-3 border-t border-line-1 pt-3">
                            {children.map((child) => (
                                <SchemaField
                                    key={child.name}
                                    param={child}
                                    value={childConfig[child.name]}
                                    onChange={(v) => handleChildChange(child.name, v)}
                                />
                            ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

export default function SchemaField({ param, value, onChange }) {
    const isBoolean = param.type === 'boolean';

    return (
        <div>
            <div className={`flex items-center gap-2 mb-1 ${isBoolean ? 'flex-row' : 'flex-col items-start'}`}>
                <label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase">
                    {param.name}
                    {param.required && <span className="text-danger ml-1">*</span>}
                    {!param.required && !isBoolean && (
                        <span className="text-fg-3 ml-2 normal-case">optional</span>
                    )}
                </label>

                {param.type === 'object' ? null : (
                    isBoolean ? (
                        <BooleanField param={param} value={value} onChange={onChange} />
                    ) : param.type === 'enum' ? (
                        <EnumField param={param} value={value} onChange={onChange} />
                    ) : param.type === 'string' ? (
                        <StringField param={param} value={value} onChange={onChange} />
                    ) : (
                        <IntegerFloatField param={param} value={value} onChange={onChange} />
                    )
                )}
            </div>

            {param.type === 'object' && (
                <ObjectField param={param} value={value} onChange={onChange} />
            )}
        </div>
    );
}
