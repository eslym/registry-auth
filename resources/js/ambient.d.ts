import { AxiosStatic } from "axios";

declare global {
	var axios: AxiosStatic;

	namespace Filter {
		type Comparable<T> =
			| {
					"=": T;
			  }
			| {
					">": T;
			  }
			| {
					"<": T;
			  }
			| {
					">=": T;
			  }
			| {
					"<=": T;
			  }
			| {
					from: T;
					to: T;
			  };

		type Types = {
			string: string;
			strings: string[];
			int: number;
			ints: number[];
			enum: string;
			enums: string[];
			numeric: Comparable<number>;
			date: Comparable<string>;
		};

		export type Meta<T extends Record<string, keyof Types>> = {
			filters: {
				[K in keyof T]?: Types[T[K]];
			};
			sort?: [string, "asc" | "desc"];
		};
	}

	namespace Model {
		export interface CurrentUser {
			id: number;
			username: string;
			is_admin: boolean;
			password_expired: boolean;
		}

		export interface User {
			id: number;
			username: string | null;
			is_admin: boolean;
			password_expired_at: string | null;
			created_at: string;
			updated_at: string;

			groups: Group[];
			groups_count: number;
			access_controls: AccessControl[];
			access_controls_count: number;
		}

		export interface Group {
			id: number;
			name: string;
			created_at: string;
			updated_at: string;

			users: User[];
			users_count: number;
			access_controls: AccessControl[];
			access_controls_count: number;
		}

		export interface AccessControl {
			id: number;
			repository: string;
			access_level: "denied" | "pull-only" | "pull-push";
		}
	}

	export interface Paginated<T, M extends Record<string, keyof Types>> {
		items: T[];
		meta: Filter.Meta<M>;
		page: {
			current: number;
			max: number;
			total: number;
			limit: number;
		};
	}
}
