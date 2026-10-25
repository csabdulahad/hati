<?php

namespace Hati\Filter;

/**
 * This enum class is used by {@link Filter::class}.
 * */

enum FilterOut
{
	
	case EMPTY;
	case ILLEGAL;
	case INVALID;
	case NULL;
	case VAL_LEN_ERROR;
	case VAL_LEN_UNDER_ERROR;
	case VAL_LEN_OVER_ERROR;
	case BYTE_LEN_ERROR;
	case BYTE_LEN_UNDER_ERROR;
	case BYTE_LEN_OVER_ERROR;
	case CODEPOINT_LEN_ERROR;
	case CODEPOINT_LEN_UNDER_ERROR;
	case CODEPOINT_LEN_OVER_ERROR;
	case REGEX_MISMATCH;
	case RANGE_ERROR;
	case RANGE_UNDER_ERROR;
	case RANGE_OVER_ERROR;
	case RANGE_FRACTION_ERROR;
	case NOT_IN_OPTION;
	case OK;
	
}
