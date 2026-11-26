// SPDX-License-Identifier: Apache-2.0
import { useT } from '../i18n';

/** A field: its translated label when there is one, and always its code (data, never translated). */
export default function FieldName({ code }) {
    const { field } = useT();
    const label = field(code);
    return (
        <span className="block">
            {label && <span className="block text-ink-200">{label}</span>}
            <code className={`break-all ${label ? 'text-[11px] text-ink-500' : 'text-xs text-ink-300'}`}>{code}</code>
        </span>
    );
}
