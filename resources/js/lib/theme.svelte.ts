import { PersistedState } from "runed";
import { MediaQuery } from "svelte/reactivity";

const userTheme = new PersistedState<"system" | "light" | "dark">(
	"theme",
	"system",
	{
		serializer: {
			serialize: (v) => v,
			deserialize: (v) => (v as any) ?? undefined
		}
	}
);
const systemTheme = new MediaQuery("(prefers-color-scheme: dark)");

let systemThemeVal: "dark" | "light" = $derived(
	systemTheme.current ? "dark" : "light"
);

let current = $derived(
	userTheme.current === "system" ? systemThemeVal : userTheme.current
);

export const theme = {
	get user() {
		return userTheme.current;
	},
	set user(value: "system" | "light" | "dark") {
		userTheme.current = value;
	},
	get current() {
		return current;
	},
	get system() {
		return systemThemeVal;
	}
};
