import type { ReactNode } from "react";

export const filtrosFilaClass =
  "flex flex-wrap items-end gap-x-2 gap-y-2 " +
  "[&>label]:w-max [&>label>span]:mb-0.5 [&>label>span]:text-xs " +
  "[&_select]:!w-max [&_select]:max-w-[12.5rem] [&_select]:!px-2 [&_select]:!py-1.5 " +
  "[&_input.w-full]:!w-[8.5rem] [&_input.w-full]:!px-2 [&_input.w-full]:!py-1.5 " +
  "[&_input[type=date]]:!w-[9.75rem] " +
  "[&>div]:w-max [&>label.flex]:w-max";

export function Filters({ children }: { children: ReactNode }) {
  return <div className={`mb-3 ${filtrosFilaClass}`}>{children}</div>;
}
