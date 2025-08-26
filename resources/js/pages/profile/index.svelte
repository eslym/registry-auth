<script lang="ts" module>
	export { default as layout } from "@/layouts/dashboard.svelte";
</script>

<script lang="ts">
	import { Config } from "@/lib/config";
	import { useForm, useInertia } from "@eslym/svelte5-inertia";
	import * as Card from "@/shadcn/ui/card";
	import * as Table from "@/shadcn/ui/table";
	import { Input } from "@/shadcn/ui/input";
	import FormErrors from "@/components/FormErrors.svelte";
	import { Label } from "@/shadcn/ui/label";
	import { buttonVariants } from "@/shadcn/ui/button";
	import LoadingButton from "@/components/LoadingButton.svelte";
	import Sorter from "@/components/Sorter.svelte";
	import Paginator from "@/components/Paginator.svelte";
	import { DateFormatter } from "@internationalized/date";
	import { fromNow } from "@/lib/date";
	import AccessTokenSheet from "@/sheets/AccessTokenSheet.svelte";
	import { watch } from "runed";
	import { EyeIcon, TrashIcon } from "@lucide/svelte";
	import ConfirmDeleteDialog from "@/dialogs/ConfirmDeleteDialog.svelte";
	import TokenCreatedDialog from "@/dialogs/TokenCreatedDialog.svelte";
	import { Badge } from "@/shadcn/ui/badge";

	const config = Config.get();

	const form = useForm({
		new_password: "",
		repeat_password: "",
		current_password: ""
	});

	const inertia = useInertia();

	let {
		tokens,
		user,
		view,
		_created
	}: {
		tokens: Paginated<
			Model.AccessToken,
			{
				search: "string";
			}
		>;
		user: Model.CurrentUser;
		view: Model.AccessToken | null;
		_created: string | null;
	} = $props();

	let currentView = $state(view);

	let created: string | null = $state(null);
	let showCreated: boolean = $state(false);

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

	let search = $state(tokens.meta.filters.search ?? "");
	let searching = false;

	function viewUrl(id: number) {
		const url = new URL(
			inertia.page.url,
			import.meta.env.SSR ? undefined : window.location.origin
		);
		url.pathname = `/profile/${id}`;
		return url.toString();
	}

	function flushSearch() {
		if (searching || search === (tokens.meta.filters.search ?? "")) return;
		searching = true;
		const url = new URL(
			inertia.page.url,
			import.meta.env.SSR ? undefined : window.location.origin
		);
		if (search) {
			url.searchParams.set("search", search);
		} else {
			url.searchParams.delete("search");
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

	watch(
		() => _created,
		() => {
			if (_created) {
				created = _created;
				inertia.router.replace({
					preserveState: true,
					preserveScroll: true,
					props({ _created, ...props }) {
						return props;
					}
				});
				setTimeout(() => (showCreated = true), 100);
			}
		}
	);

	let from = $derived(
		tokens.items.length ? (tokens.page.current - 1) * tokens.page.limit : 0
	);
	let to = $derived(from + tokens.items.length);
</script>

<svelte:head>
	<title>Profile | {config.appName}</title>
</svelte:head>

<Card.Root>
	<Card.Header>
		<Card.Title>Update Password</Card.Title>
		<Card.Action>
			<LoadingButton
				loading={form.processing}
				form="update-password"
				type="submit"
			>
				Change Password
			</LoadingButton>
		</Card.Action>
	</Card.Header>
	<Card.Content class="@container">
		<form
			id="update-password"
			action="/update-password"
			method="post"
			use:form.action
			class="grid max-w-lg grid-cols-[auto_1fr] gap-x-6 gap-y-4 @max-xs:grid-cols-1"
		>
			<div
				class="col-span-2 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label for="Username" class="justify-end @max-xs:justify-start">
					Username
				</Label>
				<Input
					id="Username"
					type="text"
					bind:value={user.username}
					readonly
				/>
			</div>
			<div
				class="col-span-2 mb-8 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label
					for="current-password"
					class="justify-end @max-xs:justify-start"
				>
					Current Password
				</Label>
				<Input
					id="current-password"
					type="password"
					bind:value={form.data.current_password}
					disabled={form.processing}
				/>
				<FormErrors
					class="col-start-2 @max-xs:col-start-1"
					errors={form.errors.current_password}
				/>
			</div>
			<div
				class="col-span-2 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label
					for="new-password"
					class="justify-end @max-xs:justify-start"
				>
					New Password
				</Label>
				<Input
					id="new-password"
					type="password"
					bind:value={form.data.new_password}
					disabled={form.processing}
				/>
				<FormErrors
					class="col-start-2 @max-xs:col-start-1"
					errors={form.errors.new_password}
				/>
			</div>
			<div
				class="col-span-2 grid grid-cols-subgrid gap-x-6 gap-y-2 @max-xs:col-span-1"
			>
				<Label
					for="repeat-password"
					class="justify-end @max-xs:justify-start"
				>
					Repeat Password
				</Label>
				<Input
					id="repeat-password"
					type="password"
					bind:value={form.data.repeat_password}
					disabled={form.processing}
				/>
				<FormErrors
					class="col-start-2 @max-xs:col-start-1"
					errors={form.errors.repeat_password}
				/>
			</div>
		</form>
	</Card.Content>
</Card.Root>
<Card.Root class="mt-6">
	<Card.Header class="max-md:px-3">
		<Card.Title>Access Tokens</Card.Title>
		<Card.Description>
			Showing {from + 1} to {to} of {tokens.page.total}.
		</Card.Description>
		<Card.Action>
			<AccessTokenSheet token={{}}>
				{#snippet children({ Trigger })}
					<Trigger class={buttonVariants()}>Create</Trigger>
				{/snippet}
			</AccessTokenSheet>
		</Card.Action>
	</Card.Header>
	<Card.Content class="max-md:px-3">
		<div class="flex flex-row flex-wrap gap-x-2 gap-y-2">
			<Input
				placeholder="Search"
				class="w-0 min-w-64 grow"
				bind:value={search}
			/>
			<Sorter
				field={tokens.meta.sort![0]}
				dir={tokens.meta.sort![1]}
				options={[
					{ field: "description", label: "Description" },
					{ field: "created_at", label: "Created At" },
					{ field: "used_at", label: "Used At" },
					{ field: "used_by", label: "Used By" },
					{ field: "expired_at", label: "Expired At" }
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
					<Table.Head>Description</Table.Head>
					<Table.Head class="w-0 text-center text-nowrap">
						Last Used At
					</Table.Head>
					<Table.Head class="w-0 text-center text-nowrap">
						Last Used IP
					</Table.Head>
					<Table.Head class="w-0 text-center text-nowrap">
						Expires
					</Table.Head>
					<Table.Head class="w-0 text-center text-nowrap">
						Created At
					</Table.Head>
					<Table.Head class="w-0"></Table.Head>
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each tokens.items as token (token.id)}
					<Table.Row>
						<Table.Cell>
							{#if token.is_refresh_token}
								<Badge
									variant="outline"
									class="text-muted-foreground"
									>refresh token</Badge
								>
							{:else}
								{token.description}
							{/if}
						</Table.Cell>
						<Table.Cell class="w-0 text-center text-nowrap">
							{#if token.last_used_at}
								{fromNow(new Date(token.last_used_at))}
							{:else}
								<span class="text-muted-foreground">Never</span>
							{/if}
						</Table.Cell>
						<Table.Cell class="w-0 text-center text-nowrap">
							{#if token.last_used_ip}
								<span class="font-mono"
									>{token.last_used_ip}</span
								>
							{:else}
								<span class="text-muted-foreground">Never</span>
							{/if}
						</Table.Cell>
						<Table.Cell class="w-0 text-center text-nowrap">
							{#if token.expired_at}
								{fromNow(new Date(token.expired_at))}
							{:else}
								<span class="text-muted-foreground">Never</span>
							{/if}
						</Table.Cell>
						<Table.Cell class="w-0 text-center text-nowrap">
							{date.format(new Date(token.created_at))}
						</Table.Cell>
						<Table.Cell class="flex justify-end gap-2">
							{#if !token.is_refresh_token}
								<a
									href={viewUrl(token.id)}
									use:inertia.link={{
										replace: true,
										preserveScroll: true
									}}
									class={buttonVariants({
										variant: "secondary",
										size: "icon"
									})}
								>
									<EyeIcon />
								</a>
							{/if}
							<ConfirmDeleteDialog
								title="Revoke {token.is_refresh_token
									? 'Refresh'
									: 'Access'} Token"
								description="Are you sure you want to revoke this {token.is_refresh_token
									? 'refresh'
									: 'access'} token? This action cannot be undone."
								replace
								preserveScroll
								preserveState
								url="/profile/{token.id}"
							>
								{#snippet children({ Trigger })}
									<Trigger
										class={buttonVariants({
											variant: "destructive",
											size: "icon"
										})}
									>
										<TrashIcon />
									</Trigger>
								{/snippet}
							</ConfirmDeleteDialog>
						</Table.Cell>
					</Table.Row>
				{:else}
					<Table.Row>
						<Table.Cell
							colspan={6}
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
		<Paginator data={tokens} replace preserveState preserveScroll />
	</Card.Footer>
</Card.Root>

{#if currentView}
	<AccessTokenSheet
		open
		token={currentView}
		editable={false}
		onclose={() => {
			const url = new URL(
				inertia.page.url,
				import.meta.env.SSR ? undefined : window.location.origin
			);
			url.pathname = "/profile";
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

<TokenCreatedDialog token={created} bind:open={showCreated} />
