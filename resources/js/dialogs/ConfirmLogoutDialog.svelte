<script lang="ts">
	import * as AlertDialog from "@/shadcn/ui/alert-dialog";
	import { buttonVariants } from "@/shadcn/ui/button";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { onDestroy, type Snippet } from "svelte";
	import { lockable } from "@eslym/svelte5-utils";

	const inertia = useInertia();

	let { children = undefined }: { children?: Snippet<[typeof AlertDialog]> } =
		$props();

	let loading = $state(false);
	const open = lockable(() => loading, false);

	onDestroy(() => open.force(false));
</script>

<AlertDialog.Root>
	{@render children?.(AlertDialog)}
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Are you sure?</AlertDialog.Title>
			<AlertDialog.Description>
				Do you really want to log out?
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel disabled={loading}>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action disabled={loading}>
				{#snippet child({ props })}
					<a
						{...props}
						href="/logout"
						use:inertia.link
						class={buttonVariants({ variant: "destructive" })}
						onvisitstart={() => (loading = true)}
						onvisitfinish={() => (loading = false)}
					>
						Logout
					</a>
				{/snippet}
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
