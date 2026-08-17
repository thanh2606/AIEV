"use client";

import { X } from "lucide-react";
import { useState } from "react";
import { useT } from "@/lib/i18n";

/**
 * Chip input dùng chung (modal tạo project, form khác):
 * gõ + Enter để thêm tag, X để xóa, tự chống trùng.
 * Hỗ trợ cả tag dạng string hoặc object { text: string }.
 */
export function TagInput({
  tags = [],
  onChange,
  id,
  placeholder,
}: {
  tags: (string | { text?: string; name?: string; label?: string } | any)[];
  onChange: (tags: any[]) => void;
  id?: string;
  /** Mặc định t("taginput.placeholder"). */
  placeholder?: string;
}) {
  const { t, tf } = useT();
  const [input, setInput] = useState("");

  function getTagLabel(tag: any): string {
    if (typeof tag === "string") return tag;
    if (tag && typeof tag === "object") {
      return tag.text || tag.name || tag.label || JSON.stringify(tag);
    }
    return String(tag);
  }

  function add() {
    const tag = input.trim();
    if (!tag) return;
    setInput("");
    if (tags.some((t) => getTagLabel(t) === tag)) return;
    onChange([...tags, tag]);
  }

  return (
    <div>
      {tags.length > 0 && (
        <div className="mb-2 flex flex-wrap gap-2">
          {tags.map((tag, idx) => {
            const label = getTagLabel(tag);
            const key = `${label}-${idx}`;
            return (
              <span key={key} className="chip">
                {label}
                <button
                  type="button"
                  aria-label={tf("taginput.remove-aria", { tag: label })}
                  className="text-[var(--text-muted)] transition-colors duration-150 hover:text-[var(--danger)]"
                  onClick={() => onChange(tags.filter((_, i) => i !== idx))}
                >
                  <X size={12} strokeWidth={2} />
                </button>
              </span>
            );
          })}
        </div>
      )}
      <input
        id={id}
        className="input"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            add();
          }
        }}
        placeholder={placeholder ?? t("taginput.placeholder")}
      />
    </div>
  );
}
