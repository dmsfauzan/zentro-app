"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type {
  BankAccount,
  Payout,
  Wallet,
  WalletTransaction,
} from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { getList } from "@/features/http";
import { useAuth } from "@/providers/AuthProvider";

export function useWallet() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.wallet,
    queryFn: () => apiFetch<Wallet>(endpoints.wallet.root, { token }),
    enabled: Boolean(token),
  });
}

export function useWalletTransactions(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.walletTransactions(filters),
    queryFn: () =>
      getList<WalletTransaction>(endpoints.wallet.transactions, token, filters),
    enabled: Boolean(token),
  });
}

export function useBankAccounts() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.bankAccounts,
    queryFn: () => apiFetch<BankAccount[]>(endpoints.wallet.bankAccounts, { token }),
    enabled: Boolean(token),
  });
}

export function useCreateBankAccount() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: {
      bank_name: string;
      account_number: string;
      account_name: string;
      is_default?: boolean;
    }) =>
      apiFetch<BankAccount>(endpoints.wallet.bankAccounts, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.bankAccounts }),
  });
}

export function usePayouts(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.payouts(filters),
    queryFn: () => getList<Payout>(endpoints.wallet.payouts, token, filters),
    enabled: Boolean(token),
  });
}

export function useRequestPayout() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      amount,
      bankAccountId,
      idempotencyKey,
    }: {
      amount: number;
      bankAccountId: number;
      idempotencyKey: string;
    }) =>
      apiFetch<Payout>(endpoints.wallet.payouts, {
        method: "POST",
        token,
        json: { amount, bank_account_id: bankAccountId },
        idempotencyKey,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["wallet"] });
      void queryClient.invalidateQueries({ queryKey: qk.wallet });
    },
  });
}

export function useCancelPayout() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payoutNumber: string) =>
      apiFetch<Payout>(endpoints.wallet.payoutCancel(payoutNumber), {
        method: "POST",
        token,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["wallet"] });
    },
  });
}
