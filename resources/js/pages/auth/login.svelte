<script lang="ts">
	import * as Card from "@/shadcn/ui/card";
	import * as Select from "@/shadcn/ui/select";
	import { Config } from "@/lib/config";
	import { theme } from "@/lib/theme.svelte";
	import { MoonIcon, SunIcon, SunMoonIcon } from "@lucide/svelte";
	import { useForm } from "@eslym/svelte5-inertia";
	import { Label } from "@/shadcn/ui/label";
	import { Input } from "@/shadcn/ui/input";
	import FormErrors from "@/components/FormErrors.svelte";
	import { Switch } from "@/shadcn/ui/switch";
	import LoadingButton from "@/components/LoadingButton.svelte";
	import FavIcon from "@/components/FavIcon.svelte";

	const config = Config.get();

	const form = useForm({
		username: "",
		password: "",
		remember: false
	});

	const icons = {
		system: SunMoonIcon,
		light: SunIcon,
		dark: MoonIcon
	};

	let Icon = $derived(icons[theme.user]);
</script>

<svelte:head>
	<title>Login | {config.appName}</title>
</svelte:head>

<div
	class="from-destructive-foreground/50 via-accent/50 to-background/50 flex h-dvh w-full items-center justify-center bg-gradient-to-br"
>
	<Card.Root class="w-full max-w-80">
		<div class="flex flex-row items-center gap-4 px-6">
			<FavIcon class="size-12"/>
			<Card.Header class="grow px-0">
				<Card.Title>Login</Card.Title>
				<Card.Description>{config.appName}</Card.Description>
				<Card.Action>
					<Select.Root type="single" bind:value={theme.user}>
						<Select.Trigger>
							<Icon />
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="system">
								<SunMoonIcon />
								System
							</Select.Item>
							<Select.Item value="light">
								<SunIcon />
								Light
							</Select.Item>
							<Select.Item value="dark">
								<MoonIcon />
								Dark
							</Select.Item>
						</Select.Content>
					</Select.Root>
				</Card.Action>
			</Card.Header>
		</div>
		<Card.Content>
			<form
				id="login-form"
				class="grid gap-4"
				method="post"
				use:form.action
			>
				<div class="grid gap-2">
					<Label for="username">Username</Label>
					<Input
						id="username"
						type="text"
						name="username"
						bind:value={form.data.username}
						disabled={form.processing}
					/>
					<FormErrors errors={form.errors.username} />
				</div>
				<div class="grid gap-2">
					<Label for="password">Password</Label>
					<Input
						id="password"
						type="password"
						name="password"
						bind:value={form.data.password}
						disabled={form.processing}
					/>
					<FormErrors errors={form.errors.password} />
				</div>
				<div class="flex flex-row items-center gap-2">
					<Label for="remember" class="grow">Remember me</Label>
					<Switch
						id="remember"
						name="remember"
						bind:checked={form.data.remember}
						disabled={form.processing}
					/>
				</div>
			</form>
		</Card.Content>
		<Card.Footer>
			<LoadingButton
				class="relative w-full"
				type="submit"
				form="login-form"
				loading={form.processing}
			>
				Login
			</LoadingButton>
		</Card.Footer>
	</Card.Root>
</div>
