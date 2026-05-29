// @vitest-environment jsdom
import { describe, test, expect } from "vitest";
import { sanitizeHtml, sanitizeUserInput } from "../../utils/DomSanitizer";

describe("DomSanitizer", () => {
  test("should allow safe HTML tags", () => {
    const input = "<p>Hello <strong>world</strong></p>";
    const output = sanitizeHtml(input);
    expect(output).toContain("<p>");
    expect(output).toContain("<strong>");
  });

  test("should remove script tags", () => {
    const input = '<p>Hello</p><script>alert("XSS")</script>';
    const output = sanitizeHtml(input);
    expect(output).not.toContain("<script>");
    expect(output).not.toContain("alert");
  });

  test("should remove event handlers", () => {
    const input = '<img src="x" onerror="alert(\'XSS\')" />';
    const output = sanitizeHtml(input);
    expect(output).not.toContain("onerror");
    expect(output).not.toContain("alert");
  });

  test("sanitizeUserInput should remove all HTML", () => {
    const input = 'User input <script>alert("XSS")</script>';
    const output = sanitizeUserInput(input);
    expect(output).not.toContain("<script>");
    expect(output).toBe('User input alert("XSS")');
  });

  test("should block data: URLs", () => {
    const input = '<a href="data:text/html,<script>alert(\'XSS\')</script>">click</a>';
    const output = sanitizeHtml(input);
    expect(output).not.toContain("data:");
  });

  test("should block javascript: URLs", () => {
    const input = '<a href="javascript:alert(\'XSS\')">click</a>';
    const output = sanitizeHtml(input);
    expect(output).not.toContain("javascript:");
  });
});
