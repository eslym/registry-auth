<script lang="ts" module>
	import { DateFormatter } from "@internationalized/date";

	export { default as layout } from "@/layouts/dashboard.svelte";
</script>

<script lang="ts">
	import { Config } from "@/lib/config";
	import * as Card from "@/shadcn/ui/card";
	import * as Table from "@/shadcn/ui/table";
	import { buttonVariants } from "@/shadcn/ui/button";
	import { Input } from "@/shadcn/ui/input";
	import Sorter from "@/components/Sorter.svelte";
	import { Badge } from "@/shadcn/ui/badge";
	import { EyeIcon, PencilLineIcon, TrashIcon } from "@lucide/svelte";
	import Paginator from "@/components/Paginator.svelte";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { watch } from "runed";
	import ConfirmDeleteDialog from "@/dialogs/ConfirmDeleteDialog.svelte";
	import UserSheet from "@/sheets/UserSheet.svelte";

	const config = Config.get();
	const inertia = useInertia();

	let date = $derived(
		new DateFormatter("en-US", {
			year: "numeric",
			month: "short",
			day: "numeric",
			hour: "2-digit",
			minute: "2-digit",
			timeZone: config.timezone
		})
	);

	let {
		user,
		users,
		view,
		groups
	}: {
		user: Model.CurrentUser;
		users: Paginated<Model.User, { username: "string"; group: "int" }>;
		view: Model.User | null;
		groups: Model.Group[];
	} = $props();

	let currentView = $state(view);

	let search = $state("");

	let from = $derived(
		users.items.length ? (users.page.current - 1) * users.page.limit : 0
	);
	let to = $derived(from + users.items.length);

	let groupMap = $derived(
		Object.fromEntries(groups.map((g) => [g.id, g.name]))
	);

	let grouped = $derived(
		users.meta.filters.group && users.meta.filters.group in groupMap
	);

	let title = $derived(
		grouped ? `Users of ${groupMap[users.meta.filters.group!]}` : "Users"
	);

	let searching = false;

	function viewUrl(id: number) {
		const url = new URL(inertia.page.url, window.location.origin);
		url.pathname = `/users/${id}`;
		return url.toString();
	}

	function flushSearch() {
		if (searching || search === (users.meta.filters.username ?? "")) return;
		searching = true;
		const url = new URL(inertia.page.url, window.location.origin);
		if (search) {
			url.searchParams.set("username", search);
		} else {
			url.searchParams.delete("username");
		}
		url.searchParams.delete("page");
		inertia.router.get(
			url,
			{},
			{
				replace: true,
				preserveState: true,
				preserveScroll: true,
				onFinish: () => {
					searching = false;
					flushSearch();
				}
			}
		);
	}

	watch(
		() => search,
		() => {
			const timeout = setTimeout(() => {
				flushSearch();
			}, 250);
			return () => clearTimeout(timeout);
		}
	);
</script>

<svelte:head>
	<title>{title} | {config.appName}</title>
</svelte:head>

<Card.Root>
	<Card.Header class="max-md:px-3">
		<Card.Title><h1>{title}</h1></Card.Title>
		<Card.Description>
			Showing {from + 1} to {to} of {users.page.total}.
		</Card.Description>
		{#if !grouped && user.is_admin}
			<Card.Action>
				<UserSheet
					user={{}}
					{groups}
					replace
					preserveState
					preserveScroll
				>
					{#snippet children({ Trigger })}
						<Trigger class={buttonVariants()}>Create</Trigger>
					{/snippet}
				</UserSheet>
			</Card.Action>
		{/if}
	</Card.Header>
	<Card.Content class="max-md:px-3">
		<div class="flex flex-row flex-wrap gap-x-2 gap-y-2">
			<Input
				placeholder="Search"
				class="w-0 min-w-64 grow"
				bind:value={search}
			/>
			<Sorter
				field={users.meta.sort![0]}
				dir={users.meta.sort![1]}
				options={[
					{ field: "username", label: "Username" },
					{ field: "created_at", label: "Created At" }
				]}
				class="ml-auto w-40"
				replace
				preserveState
				preserveScroll
			/>
		</div>
		<Table.Root class="mt-4">
			<Table.Header>
				<Table.Row>
					<Table.Head colspan={2}>Username</Table.Head>
					<Table.Head class="w-0 text-center text-nowrap">
						Created At
					</Table.Head>
					<Table.Head class="w-0"></Table.Head>
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each users.items as record (record.id)}
					<Table.Row>
						<Table.Cell>
							{#if record.username !== null}
								{record.username}
							{:else}
								<span class="text-muted-foreground">
									(anonymous user)
								</span>
							{/if}
						</Table.Cell>
						<Table.Cell class="w-0">
							{#if record.is_admin}
								<Badge>Admin</Badge>
							{/if}
						</Table.Cell>
						<Table.Cell>
							{date.format(new Date(record.created_at))}
						</Table.Cell>
						<Table.Cell>
							<a
								href={viewUrl(record.id)}
								use:inertia.link={{
									replace: true,
									preserveScroll: true
								}}
								class={buttonVariants({
									variant: "secondary",
									size: "icon"
								})}
							>
								{#if user.is_admin}
									<PencilLineIcon />
								{:else}
									<EyeIcon />
								{/if}
							</a>
							{#if user.is_admin}
								<ConfirmDeleteDialog
									url="/users/{record.id}"
									replace
									preserveScroll
								>
									{#snippet children({ Trigger })}
										<Trigger
											class={buttonVariants({
												variant: "destructive",
												size: "icon"
											})}
											disabled={record.username ===
												null || record.id === user.id}
										>
											<TrashIcon />
										</Trigger>
									{/snippet}
								</ConfirmDeleteDialog>
							{/if}
						</Table.Cell>
					</Table.Row>
				{:else}
					<Table.Row>
						<Table.Cell
							colspan={4}
							class="text-center h-20 text-muted-foreground"
						>
							No records.
						</Table.Cell>
					</Table.Row>
				{/each}
			</Table.Body>
		</Table.Root>
	</Card.Content>
	<Card.Footer>
		<Paginator data={users} replace preserveState preserveScroll />
	</Card.Footer>
</Card.Root>

{#if currentView}
	<UserSheet
		open
		user={currentView}
		{groups}
		editable={user.is_admin}
		anonymous={currentView.username === null || user.id === currentView?.id}
		onclose={() => {
			const url = new URL(inertia.page.url, window.location.origin);
			url.pathname = "/users";
			inertia.router.replace({
				url: url.toString(),
				preserveState: true,
				preserveScroll: true,
				props(props) {
					delete props.view;
					return props;
				}
			});
		}}
		replace
		preserveState
		preserveScroll
	/>
{/if}
