import React from "react";
import { sanitizeHtml, sanitizeUserInput } from "../utils/DomSanitizer";

interface SanitizedInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "onChange"> {
  value: string;
  onChange: (value: string) => void;
}

/**
 * Sanitized input component: removes XSS vectors
 */
export function SanitizedInput({ value, onChange, ...props }: SanitizedInputProps) {
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const sanitized = sanitizeUserInput(e.target.value);
    onChange(sanitized);
  };

  return <input {...props} value={value} onChange={handleChange} />;
}

/**
 * Render user-generated content safely
 */
export function SafeHtmlContent({ html }: { html: string }) {
  const sanitized = sanitizeHtml(html);
  return <div dangerouslySetInnerHTML={{ __html: sanitized }} />;
}
