<script lang="ts">
	import * as DropdownMenu from "@/shadcn/ui/dropdown-menu";
	import { buttonVariants, Button } from "@/shadcn/ui/button";
	import type { Snippet } from "svelte";
	import PasswordExpiredDialog from "@/dialogs/PasswordExpiredDialog.svelte";
	import { useInertia } from "@eslym/svelte5-inertia";
	import { Separator } from "@/shadcn/ui/separator";
	import { Config } from "@/lib/config";
	import {
		UsersIcon,
		GroupIcon,
		SunMoonIcon,
		SunIcon,
		MoonIcon,
		LogOutIcon,
		UserRoundCogIcon
	} from "@lucide/svelte";
	import { theme } from "@/lib/theme.svelte";
	import ConfirmLogoutDialog from "@/dialogs/ConfirmLogoutDialog.svelte";
	import UpdatePasswordDialog from "@/dialogs/UpdatePasswordDialog.svelte";

	const config = Config.get();
	const inertia = useInertia();

	let {
		children,
		user,
		route
	}: { children: Snippet; user: Model.CurrentUser; route?: string } =
		$props();

	const icons = {
		system: SunMoonIcon,
		light: SunIcon,
		dark: MoonIcon
	};

	let Icon = $derived(icons[theme.user]);
</script>

<div class="mx-auto w-full max-w-5xl px-4 pt-6 pb-12 max-sm:pt-4">
	<header class="mb-12">
		<nav>
			<ul class="flex flex-row items-center gap-1 max-sm:gap-0.5">
				<li class="mr-2 flex items-center">
					<img src="/favicon.svg" class="size-9" alt="logo" />
					<span class="ml-2 text-lg font-semibold max-sm:sr-only">
						{config.appName}
					</span>
				</li>
				<li class="ml-auto">
					<a
						href="/users"
						class={buttonVariants({
							variant: "ghost",
							class: [
								route?.startsWith("users.") &&
									"text-primary/90 hover:text-primary"
							]
						})}
						use:inertia.link
					>
						<span class="max-sm:sr-only">Users</span>
						<UsersIcon />
					</a>
				</li>
				<li>
					<a
						href="/groups"
						class={buttonVariants({
							variant: "ghost",
							class: [
								route?.startsWith("groups.") &&
									"text-primary/90 hover:text-primary"
							]
						})}
						use:inertia.link
					>
						<span class="max-sm:sr-only">Groups</span>
						<GroupIcon />
					</a>
				</li>
				<li class="flex items-center self-stretch">
					<Separator orientation="vertical" class="!h-6/10" />
				</li>
				<li>
					<UpdatePasswordDialog>
						{#snippet children({ Trigger })}
							<Trigger
								class={buttonVariants({
									variant: "ghost",
									size: "icon"
								})}
							>
								<span class="sr-only">Update Password</span>
								<UserRoundCogIcon />
							</Trigger>
						{/snippet}
					</UpdatePasswordDialog>
				</li>
				<li>
					<ConfirmLogoutDialog>
						{#snippet children({ Trigger })}
							<Trigger
								class={buttonVariants({
									variant: "ghost",
									size: "icon"
								})}
							>
								<LogOutIcon />
								<span class="sr-only">Logout</span>
							</Trigger>
						{/snippet}
					</ConfirmLogoutDialog>
				</li>
				<li class="flex items-center self-stretch">
					<Separator orientation="vertical" class="!h-6/10" />
				</li>
				<li>
					<DropdownMenu.Root>
						<DropdownMenu.Trigger>
							{#snippet child({ props })}
								<Button {...props} variant="ghost" size="icon">
									<Icon />
									<span class="sr-only">Theme</span>
								</Button>
							{/snippet}
						</DropdownMenu.Trigger>
						<DropdownMenu.Content align="end">
							<DropdownMenu.RadioGroup bind:value={theme.user}>
								<DropdownMenu.RadioItem value="system">
									System
									<SunMoonIcon class="ml-auto" />
								</DropdownMenu.RadioItem>
								<DropdownMenu.RadioItem value="light">
									Light
									<SunIcon class="ml-auto" />
								</DropdownMenu.RadioItem>
								<DropdownMenu.RadioItem value="dark">
									Dark
									<MoonIcon class="ml-auto" />
								</DropdownMenu.RadioItem>
							</DropdownMenu.RadioGroup>
						</DropdownMenu.Content>
					</DropdownMenu.Root>
				</li>
			</ul>
		</nav>
	</header>
	{@render children()}
</div>

<PasswordExpiredDialog bind:open={user.password_expired} />
