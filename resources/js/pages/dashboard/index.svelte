<script lang="ts" module>
	import * as Card from "@/shadcn/ui/card";

	export { default as layout } from "@/layouts/dashboard.svelte";

	const format = new Intl.NumberFormat("en-US");
</script>

<script lang="ts">
	import { Config } from "@/lib/config";
	import { buttonVariants } from "@/shadcn/ui/button";
	import { useInertia } from "@eslym/svelte5-inertia";

	const config = Config.get();
	const inertia = useInertia();

	let { users, groups }: { users: number; groups: number } = $props();
</script>

<svelte:head>
	<title>Dashboard | {config.appName}</title>
</svelte:head>

<div
	class="grid grid-cols-[repeat(auto-fill,minmax(16rem,1fr))] gap-x-4 gap-y-6"
>
	<Card.Root>
		<Card.Header class="text-xl">
			<Card.Title>Users</Card.Title>
			<Card.Action class="text-muted-foreground">{users}</Card.Action>
		</Card.Header>
		<Card.Footer class="justify-end">
			<a
				href="/users"
				class={buttonVariants({ variant: "secondary" })}
				use:inertia.link
			>
				View Users
			</a>
		</Card.Footer>
	</Card.Root>
	<Card.Root>
		<Card.Header class="text-xl">
			<Card.Title>Groups</Card.Title>
			<Card.Action class="text-muted-foreground">{groups}</Card.Action>
		</Card.Header>
		<Card.Footer class="justify-end">
			<a
				href="/groups"
				class={buttonVariants({ variant: "secondary" })}
				use:inertia.link
			>
				View Groups
			</a>
		</Card.Footer>
	</Card.Root>
</div>
