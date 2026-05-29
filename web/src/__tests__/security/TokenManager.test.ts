// @vitest-environment jsdom
import { describe, test, expect, beforeEach } from "vitest";
import { TokenManager } from "../../auth/TokenManager";

describe("TokenManager", () => {
  let tm: TokenManager;

  beforeEach(() => {
    tm = new TokenManager();
    localStorage.clear();
  });

  test("should store and retrieve token", () => {
    tm.setToken("test-token", 3600);
    expect(tm.getToken()).toBe("test-token");
  });

  test("should clear token on demand", () => {
    tm.setToken("test-token", 3600);
    tm.clearToken();
    expect(tm.getToken()).toBeNull();
  });

  test("should return null for expired token", () => {
    tm.setToken("test-token", -1); // Already expired
    expect(tm.getToken()).toBeNull();
  });

  test("should not use localStorage", () => {
    tm.setToken("test-token", 3600);
    expect(localStorage.getItem("token")).toBeNull();
    expect(localStorage.getItem("access_token")).toBeNull();
  });

  test("should report authenticated status correctly", () => {
    expect(tm.isAuthenticated()).toBe(false);
    tm.setToken("test-token", 3600);
    expect(tm.isAuthenticated()).toBe(true);
    tm.clearToken();
    expect(tm.isAuthenticated()).toBe(false);
  });
});
